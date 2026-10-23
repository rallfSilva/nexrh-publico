<?php

namespace App\Http\Controllers;

use App\Ferias\FormularioPedido;
use App\Ferias\Marcacao;
use App\Ferias\Periodos;
use App\Models\Documento;
use App\Servidores\ArquivoDossie;
use App\Servidores\FichaPdf;
use App\Support\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** O próprio servidor consulta a ficha, baixa o PDF, envia documentos para o dossiê e pede férias. */
class MinhaFichaController extends Controller
{
    /** Atos funcionais são publicados pelo órgão: só o RH inclui. */
    public const CATEGORIAS_DO_SERVIDOR = ['identificacao', 'escolaridade', 'admissao', 'capacitacao', 'dependentes', 'saude', 'outros'];

    public function show(Request $request, Periodos $periodos): View
    {
        $servidor = $request->user()->servidor;

        if ($servidor === null) {
            return view('servidores.sem-ficha');
        }

        $periodos->sincronizar($servidor);
        $servidor->load(['unidade.chefe', 'vaga.cargo', 'declaracoes', 'eventos', 'documentos', 'dependentes', 'periodosAquisitivos.ferias', 'ferias.analisadoPor', 'afastamentos']);

        return view('servidores.show', [
            'servidor' => $servidor,
            'completo' => true,
            'propria' => true,
            'pendencias' => [...$servidor->pendencias(), ...$servidor->pendenciasDoDossie(), ...$servidor->pendenciasDeFerias()],
        ]);
    }

    public function pedirFerias(Request $request, FormularioPedido $formulario): View
    {
        $servidor = $request->user()->servidor ?? abort(404);
        abort_unless($servidor->ativo(), 403);

        return view('ferias.form', $formulario->dados($request, $servidor, true));
    }

    /** O pedido fica aguardando a aprovação da chefia (ou do RH, se o setor não tem chefe). */
    public function enviarPedidoFerias(Request $request, Marcacao $marcacao): RedirectResponse
    {
        $servidor = $request->user()->servidor ?? abort(404);
        abort_unless($servidor->ativo(), 403);
        $dados = FormularioPedido::validar($request, false);

        $ferias = $marcacao->marcar(
            $servidor,
            $servidor->periodosAquisitivos()->findOrFail($dados['periodo_aquisitivo_id']),
            Carbon::parse($dados['inicio']),
            (int) $dados['dias'],
            $request->user(),
            true,
        );

        return redirect()->route('minha-ficha', ['aba' => 'ferias'])
            ->with('sucesso', "Pedido enviado: {$ferias->periodoTexto()} ({$ferias->dias} dias). ".($servidor->unidade?->chefe ? $servidor->unidade->chefe->name.' (chefia)' : 'O RH').' vai analisar.');
    }

    public function sugestoesFerias(Request $request, FormularioPedido $formulario): JsonResponse
    {
        $servidor = $request->user()->servidor ?? abort(404);

        return response()->json($formulario->sugestoes($servidor, $request, true));
    }

    /** O servidor desiste do próprio pedido enquanto ainda não foi analisado. */
    public function desistirFerias(Request $request, int $ferias): RedirectResponse
    {
        $servidor = $request->user()->servidor ?? abort(404);
        $pedido = $servidor->ferias()->findOrFail($ferias);
        abort_unless($pedido->situacao === 'programada', 409);

        $pedido->update(['situacao' => 'cancelada', 'motivo' => 'Pedido retirado pelo próprio servidor.', 'analisado_por' => $request->user()->id, 'analisado_em' => now()]);

        return redirect()->route('minha-ficha', ['aba' => 'ferias'])->with('sucesso', "Pedido de {$pedido->periodoTexto()} retirado. O saldo voltou para o período.");
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
