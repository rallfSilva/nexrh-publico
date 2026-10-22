<?php

namespace App\Http\Controllers;

use App\Importacao\AnalisadorQuadroCargos;
use App\Importacao\AnalisadorServidores;
use App\Importacao\AplicadorQuadroCargos;
use App\Importacao\AplicadorServidores;
use App\Models\Importacao;
use App\Models\Orgao;
use App\Support\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class ImportacaoController extends Controller
{
    public function index(Request $request): View
    {
        return view('importacoes.index', [
            'importacoes' => Importacao::with(['usuario', 'orgao'])
                ->whereIn('tipo', $this->tiposPermitidos($request))
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $tipos = $this->tiposPermitidos($request);
        $tipo = in_array($request->input('tipo'), $tipos, true) ? $request->input('tipo') : $tipos[0];

        return view('importacoes.create', [
            'tipo' => $tipo,
            'tipos' => $tipos,
            'orgaos' => $request->user()->orgao_id === null ? Orgao::where('ativo', true)->orderBy('sigla')->get() : collect(),
        ]);
    }

    public function store(Request $request, AnalisadorQuadroCargos $cargos, AnalisadorServidores $servidores): RedirectResponse
    {
        $user = $request->user();
        $request->mergeIfMissing(['tipo' => $this->tiposPermitidos($request)[0] ?? null]);

        $request->validate([
            'tipo' => ['required', Rule::in($this->tiposPermitidos($request))],
            'arquivo' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'orgao_id' => [$user->orgao_id === null ? 'required' : 'nullable', 'exists:orgaos,id'],
        ], [
            'arquivo.mimes' => 'Envie a planilha no formato .xlsx (Excel).',
            'arquivo.max' => 'A planilha pode ter no máximo 10 MB.',
            'orgao_id.required' => 'Escolha o órgão.',
        ]);

        $tipo = $request->input('tipo');
        $orgaoId = $user->orgao_id ?? (int) $request->input('orgao_id');
        $arquivo = $request->file('arquivo');
        $caminho = $arquivo->storeAs("importacoes/{$orgaoId}", Str::uuid().'.xlsx', 'local');
        $completo = Storage::disk('local')->path($caminho);

        try {
            $resultado = $tipo === 'servidores' ? $servidores->analisar($completo, $orgaoId) : $cargos->analisar($completo);
        } catch (RuntimeException $e) {
            Storage::disk('local')->delete($caminho);

            return back()->withErrors(['arquivo' => $e->getMessage()]);
        }

        $bloqueios = array_filter($resultado['divergencias'], fn ($d) => $d['nivel'] === 'bloqueia');
        $chave = $tipo === 'servidores' ? 'servidores' : 'cargos';

        $importacao = Importacao::create([
            'orgao_id' => $orgaoId,
            'user_id' => $user->id,
            'tipo' => $tipo,
            'formato' => $resultado['formato'],
            'arquivo_nome' => mb_substr($arquivo->getClientOriginalName(), 0, 255),
            'arquivo_caminho' => $caminho,
            'arquivo_hash' => hash_file('sha256', $completo),
            'situacao' => $bloqueios === [] ? 'analisada' : 'recusada',
            'resumo' => $resultado['resumo'],
            'divergencias' => $resultado['divergencias'],
            'dados' => $bloqueios === [] ? [$chave => $resultado[$chave]] : null,
        ]);

        Auditoria::registrar('importacao', $importacao, "Planilha \"{$importacao->arquivo_nome}\" enviada para análise (".count($resultado['divergencias']).' divergência(s))');

        return redirect()->route('importacoes.show', $importacao);
    }

    public function show(Request $request, Importacao $importacao): View
    {
        $this->autorizar($request, $importacao);
        $chave = $importacao->tipo === 'servidores' ? 'servidores' : 'cargos';
        $previa = $importacao->situacao === 'analisada' ? ($importacao->dados[$chave] ?? []) : [];

        return view('importacoes.show', ['importacao' => $importacao, 'previa' => $previa]);
    }

    public function aplicar(Request $request, Importacao $importacao, AplicadorQuadroCargos $cargos, AplicadorServidores $servidores): RedirectResponse
    {
        $this->autorizar($request, $importacao);

        if (! $importacao->podeAplicar()) {
            return back()->with('erro', 'Esta importação não pode mais ser aplicada.');
        }

        if ($importacao->tipo === 'servidores') {
            $r = $servidores->aplicar($importacao);

            return redirect()->route('servidores.index')->with('sucesso', sprintf(
                'Servidores importados: %d novo(s), %d atualizado(s), %d com vaga vinculada e %d declaração(ões). O relatório continua no histórico de importações.',
                $r['criados'], $r['atualizados'], $r['com_vaga'], $r['declaracoes'],
            ));
        }

        $r = $cargos->aplicar($importacao);

        return redirect()->route('cargos.index', ['orgao' => $importacao->orgao_id])->with('sucesso', sprintf(
            'Quadro importado: %d cargo(s) novo(s), %d atualizado(s), %d vaga(s). O relatório de divergências continua disponível no histórico de importações.',
            $r['criados'], $r['atualizados'], $r['vagas'],
        ));
    }

    public function cancelar(Request $request, Importacao $importacao): RedirectResponse
    {
        $this->autorizar($request, $importacao);

        if ($importacao->situacao !== 'analisada') {
            return back()->with('erro', 'Esta importação não pode mais ser cancelada.');
        }

        $importacao->forceFill(['situacao' => 'cancelada', 'dados' => null])->save();
        Storage::disk('local')->delete($importacao->arquivo_caminho);
        Auditoria::registrar('importacao', $importacao, "Importação de \"{$importacao->arquivo_nome}\" cancelada");

        return redirect()->route('importacoes.index')->with('sucesso', 'Importação cancelada. Nada foi alterado.');
    }

    /** Cargos para quem gerencia cargos; servidores para quem gerencia servidores. */
    private function tiposPermitidos(Request $request): array
    {
        $user = $request->user();

        return array_values(array_filter([
            $user->can('cargos.gerenciar') ? 'cargos' : null,
            $user->can('servidores.gerenciar') ? 'servidores' : null,
        ]));
    }

    private function autorizar(Request $request, Importacao $importacao): void
    {
        abort_unless(in_array($importacao->tipo, $this->tiposPermitidos($request), true), 404);
    }
}
