<?php

namespace App\Servidores;

use App\Models\Documento;
use App\Models\Servidor;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarda e lê os arquivos do dossiê.
 *
 * O conteúdo é criptografado com a chave da aplicação antes de ir para o disco
 * privado, então uma cópia da pasta de arquivos sozinha não expõe documentos.
 * O hash SHA-256 do original é conferido a cada leitura.
 */
class ArquivoDossie
{
    private const DISCO = 'local';

    /** @return array{arquivo_caminho: string, arquivo_nome: string, arquivo_mime: string, arquivo_tamanho: int, arquivo_hash: string} */
    public function guardar(Servidor $servidor, UploadedFile $arquivo): array
    {
        return $this->guardarConteudo($servidor, (string) file_get_contents($arquivo->getRealPath()), $arquivo->getClientOriginalName(), self::tipoReal($arquivo));
    }

    /** Tipo pelo conteúdo do arquivo (assinatura), ignorando extensão e o que o navegador informou. */
    public static function tipoReal(mixed $arquivo): string
    {
        return $arquivo instanceof UploadedFile && $arquivo->isValid()
            ? (string) (new finfo(FILEINFO_MIME_TYPE))->file($arquivo->getRealPath())
            : '';
    }

    /** @return array{arquivo_caminho: string, arquivo_nome: string, arquivo_mime: string, arquivo_tamanho: int, arquivo_hash: string} */
    public function guardarConteudo(Servidor $servidor, string $conteudo, string $nomeOriginal, string $mime): array
    {
        if (! array_key_exists($mime, Documento::MIMES)) {
            throw new RuntimeException('Tipo de arquivo não aceito.');
        }

        $caminho = "dossie/{$servidor->orgao_id}/{$servidor->id}/".Str::uuid().'.bin';
        Storage::disk(self::DISCO)->put($caminho, Crypt::encryptString($conteudo));

        return [
            'arquivo_caminho' => $caminho,
            'arquivo_nome' => $this->nomeSeguro($nomeOriginal, Documento::MIMES[$mime]),
            'arquivo_mime' => $mime,
            'arquivo_tamanho' => strlen($conteudo),
            'arquivo_hash' => hash('sha256', $conteudo),
        ];
    }

    public function conteudo(Documento $documento): string
    {
        $cifrado = Storage::disk(self::DISCO)->get($documento->arquivo_caminho)
            ?? throw new RuntimeException('Arquivo do documento não encontrado.');
        $conteudo = Crypt::decryptString($cifrado);

        if (! hash_equals($documento->arquivo_hash, hash('sha256', $conteudo))) {
            throw new RuntimeException('O arquivo do documento não confere com o original.');
        }

        return $conteudo;
    }

    /**
     * Entrega o arquivo para abrir no navegador ou baixar. O tipo vem do conteúdo
     * conferido no envio e o navegador é proibido de adivinhar outro (nosniff).
     * Imagens abrem em sandbox; PDF não, porque o leitor de PDF do Chrome não
     * funciona com sandbox.
     */
    public function resposta(Documento $documento, bool $baixar): Response
    {
        $nome = $documento->arquivo_nome;
        $cabecalhos = [
            'Content-Type' => $documento->arquivo_mime,
            'Content-Disposition' => ($baixar ? 'attachment' : 'inline').'; filename="'.Str::ascii($nome).'"; filename*=UTF-8\'\''.rawurlencode($nome),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ];

        if ($documento->ehImagem()) {
            $cabecalhos['Content-Security-Policy'] = "sandbox; default-src 'none'; img-src 'self'; style-src 'unsafe-inline'";
        }

        return response($this->conteudo($documento), 200, $cabecalhos);
    }

    /** Nome sem caminho, sem caracteres de controle e com a extensão do tipo real. */
    private function nomeSeguro(string $nome, string $extensao): string
    {
        $base = pathinfo(basename(str_replace('\\', '/', $nome)), PATHINFO_FILENAME);
        $base = trim(preg_replace('/[^\pL\pN ._()-]+/u', '_', $base) ?? '', ' ._');

        return mb_substr($base !== '' ? $base : 'documento', 0, 120).'.'.$extensao;
    }
}
