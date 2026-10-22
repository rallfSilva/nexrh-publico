<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Servidores\ArquivoDossie;
use App\Servidores\FichaPdf;
use App\Support\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** O próprio servidor consulta a ficha, baixa o PDF e envia documentos para o dossiê. */
class MinhaFichaController extends Controller
{
    /** Atos funcionais são publicados pelo órgão: só o RH inclui. */
    public const CATEGORIAS_DO_SERVIDOR = ['identificacao', 'escolaridade', 'admissao', 'capacitacao', 'dependentes', 'saude', 'outros'];

    public function show(Request $request): View
    {
        $servidor = $request->user()->servidor;

        if ($servidor === null) {
            return view('servidores.sem-ficha');
        }

        $servidor->load(['unidade.chefe', 'vaga.cargo', 'declaracoes', 'eventos', 'documentos', 'dependentes']);

        return view('servidores.show', [
            'servidor' => $servidor,
            'completo' => true,
            'propria' => true,
            'pendencias' => [...$servidor->pendencias(), ...$servidor->pendenciasDoDossie()],
        ]);
    }

    public function pdf(Request $request, FichaPdf $ficha): Response
    {
        $servidor = $request->user()->servidor ?? abort(404);

        Auditoria::registrar('download', $servidor, 'Ficha cadastral (PDF) baixada pelo próprio servidor');

        return $ficha->baixar($servidor);
    }

    /** O servidor envia; o documento fica aguardando a validação do RH. */
    public function enviarDocumento(Request $request, ArquivoDossie $arquivos): RedirectResponse
    {
        $servidor = $request->user()->servidor ?? abort(404);
        abort_unless($servidor->ativo(), 403);

        $dados = DocumentoController::validarEnvio($request, self::CATEGORIAS_DO_SERVIDOR);

        $documento = Documento::create([
            ...$dados,
            ...$arquivos->guardar($servidor, $request->file('arquivo')),
            'orgao_id' => $servidor->orgao_id,
            'servidor_id' => $servidor->id,
            'situacao' => 'pendente',
            'enviado_por' => $request->user()->id,
            'enviado_pelo_servidor' => true,
        ]);

        return redirect()->route('minha-ficha', ['aba' => 'dossie'])
            ->with('sucesso', "\"{$documento->titulo()}\" enviado. O RH vai conferir e validar.");
    }

    public function arquivo(Request $request, int $documento, ArquivoDossie $arquivos): Response
    {
        $servidor = $request->user()->servidor ?? abort(404);
        $doc = $servidor->documentos()->findOrFail($documento);
        $baixar = $request->boolean('baixar');

        Auditoria::registrar($baixar ? 'download' : 'acesso', $doc, ($baixar ? 'Download' : 'Visualização').' de "'.$doc->titulo().'" pelo próprio servidor');

        return $arquivos->resposta($doc, $baixar);
    }
}
