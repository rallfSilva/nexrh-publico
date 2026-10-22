<?php

namespace App\Http\Controllers;

use App\Models\Documento;
use App\Models\Servidor;
use App\Servidores\ArquivoDossie;
use App\Support\Auditoria;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dossiê digital, lado do RH: envio (já validado), validação ou recusa do que o
 * servidor enviou, arquivamento e a fila de documentos a validar.
 */
class DocumentoController extends Controller
{
    public function __construct(private ArquivoDossie $arquivos) {}

    public function pendentes(Request $request): View
    {
        return view('documentos.pendentes', [
            'documentos' => Documento::with(['servidor.unidade', 'enviadoPor'])
                ->where('situacao', 'pendente')
                ->whereHas('servidor', fn ($q) => $q->visiveisPara($request->user()))
                ->oldest()
                ->paginate(30),
        ]);
    }

    public function store(Request $request, Servidor $servidor): RedirectResponse
    {
        $dados = self::validarEnvio($request);

        $documento = Documento::create([
            ...$dados,
            ...$this->arquivos->guardar($servidor, $request->file('arquivo')),
            'orgao_id' => $servidor->orgao_id,
            'servidor_id' => $servidor->id,
            'situacao' => 'validado',
            'enviado_por' => $request->user()->id,
            'analisado_por' => $request->user()->id,
            'analisado_em' => now(),
        ]);

        return redirect()->route('servidores.show', [$servidor, 'aba' => 'dossie'])
            ->with('sucesso', "\"{$documento->titulo()}\" incluído no dossiê.");
    }

    public function arquivo(Request $request, Servidor $servidor, Documento $documento): Response
    {
        $baixar = $request->boolean('baixar');
        Auditoria::registrar($baixar ? 'download' : 'acesso', $documento, ($baixar ? 'Download' : 'Visualização').' de "'.$documento->titulo().'" de '.$servidor->nome);

        return $this->arquivos->resposta($documento, $baixar);
    }

    public function validar(Request $request, Servidor $servidor, Documento $documento): RedirectResponse
    {
        abort_unless($documento->situacao === 'pendente', 409);

        $documento->update(['situacao' => 'validado', 'motivo' => null, 'analisado_por' => $request->user()->id, 'analisado_em' => now()]);

        return $this->voltar($request, $servidor, "\"{$documento->titulo()}\" validado.");
    }

    public function recusar(Request $request, Servidor $servidor, Documento $documento): RedirectResponse
    {
        abort_unless($documento->situacao === 'pendente', 409);
        $motivo = $request->validate(['motivo' => ['required', 'string', 'max:255']], ['motivo.required' => 'Diga ao servidor por que o documento foi recusado.'])['motivo'];

        $documento->update(['situacao' => 'recusado', 'motivo' => $motivo, 'analisado_por' => $request->user()->id, 'analisado_em' => now()]);

        return $this->voltar($request, $servidor, "\"{$documento->titulo()}\" recusado. O servidor vê o motivo na ficha dele.");
    }

    public function arquivar(Request $request, Servidor $servidor, Documento $documento): RedirectResponse
    {
        abort_if($documento->situacao === 'arquivado', 409);
        $motivo = $request->validate(['motivo' => ['required', 'string', 'max:255']], ['motivo.required' => 'Informe por que o documento vai ser arquivado.'])['motivo'];

        $documento->update(['situacao' => 'arquivado', 'motivo' => $motivo, 'analisado_por' => $request->user()->id, 'analisado_em' => now()]);

        return $this->voltar($request, $servidor, "\"{$documento->titulo()}\" arquivado. Continua guardado, fora da lista principal.");
    }

    /**
     * Regras do envio, iguais para o RH e para o servidor. A categoria sai do tipo.
     *
     * @return array<string, mixed>
     */
    public static function validarEnvio(Request $request, array $categoriasPermitidas = []): array
    {
        $tipos = $categoriasPermitidas === []
            ? Documento::todosOsTipos()
            : array_merge(...array_values(array_intersect_key(Documento::TIPOS, array_flip($categoriasPermitidas))));

        $dados = $request->validate([
            'tipo' => ['required', Rule::in(array_keys($tipos))],
            'descricao' => ['nullable', 'string', 'max:160', Rule::requiredIf(in_array($request->input('tipo'), ['outro', 'certificado_curso', 'portaria'], true))],
            'data_documento' => ['nullable', 'date', 'after:1900-01-01', 'before_or_equal:today'],
            'validade' => ['nullable', 'date', 'after_or_equal:data_documento'],
            'arquivo' => ['required', 'file', 'max:'.Documento::TAMANHO_MAXIMO_KB, function (string $atributo, $arquivo, Closure $falha) {
                // Confere o conteúdo, não a extensão nem o tipo informado pelo navegador.
                if (! array_key_exists(ArquivoDossie::tipoReal($arquivo), Documento::MIMES)) {
                    $falha('Envie PDF, JPG ou PNG.');
                }
            }],
        ], [
            'tipo.required' => 'Escolha o tipo de documento.',
            'tipo.in' => 'Escolha um tipo da lista.',
            'descricao.required' => 'Descreva o documento. Ex.: nome do curso ou número da portaria.',
            'data_documento.before_or_equal' => 'A data do documento não pode ser no futuro.',
            'validade.after_or_equal' => 'A validade não pode ser antes da data do documento.',
            'arquivo.required' => 'Escolha o arquivo.',
            'arquivo.max' => 'O arquivo pode ter no máximo 10 MB.',
            'arquivo.uploaded' => 'O arquivo não chegou inteiro. Tente de novo (máximo de 10 MB).',
        ]);

        foreach (Documento::TIPOS as $categoria => $lista) {
            if (array_key_exists($dados['tipo'], $lista)) {
                $dados['categoria'] = $categoria;
            }
        }
        unset($dados['arquivo']);

        return $dados;
    }

    private function voltar(Request $request, Servidor $servidor, string $mensagem): RedirectResponse
    {
        $destino = $request->input('voltar') === 'fila'
            ? redirect()->route('documentos.pendentes')
            : redirect()->route('servidores.show', [$servidor, 'aba' => 'dossie']);

        return $destino->with('sucesso', $mensagem);
    }
}
