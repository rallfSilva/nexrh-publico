<?php

namespace App\Http\Controllers;

use App\Importacao\AnalisadorQuadroCargos;
use App\Importacao\AplicadorQuadroCargos;
use App\Models\Importacao;
use App\Models\Orgao;
use App\Support\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class ImportacaoController extends Controller
{
    public function index(): View
    {
        return view('importacoes.index', [
            'importacoes' => Importacao::with(['usuario', 'orgao'])->where('tipo', 'cargos')->latest('id')->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        return view('importacoes.create', [
            'orgaos' => $request->user()->orgao_id === null ? Orgao::where('ativo', true)->orderBy('sigla')->get() : collect(),
        ]);
    }

    public function store(Request $request, AnalisadorQuadroCargos $analisador): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'arquivo' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'orgao_id' => [$user->orgao_id === null ? 'required' : 'nullable', 'exists:orgaos,id'],
        ], [
            'arquivo.mimes' => 'Envie a planilha no formato .xlsx (Excel).',
            'arquivo.max' => 'A planilha pode ter no máximo 10 MB.',
            'orgao_id.required' => 'Escolha o órgão.',
        ]);

        $orgaoId = $user->orgao_id ?? (int) $request->input('orgao_id');
        $arquivo = $request->file('arquivo');
        $caminho = $arquivo->storeAs("importacoes/{$orgaoId}", Str::uuid().'.xlsx', 'local');

        try {
            $resultado = $analisador->analisar(Storage::disk('local')->path($caminho));
        } catch (RuntimeException $e) {
            Storage::disk('local')->delete($caminho);

            return back()->withErrors(['arquivo' => $e->getMessage()]);
        }

        $bloqueios = array_filter($resultado['divergencias'], fn ($d) => $d['nivel'] === 'bloqueia');

        $importacao = Importacao::create([
            'orgao_id' => $orgaoId,
            'user_id' => $user->id,
            'tipo' => 'cargos',
            'formato' => $resultado['formato'],
            'arquivo_nome' => mb_substr($arquivo->getClientOriginalName(), 0, 255),
            'arquivo_caminho' => $caminho,
            'arquivo_hash' => hash_file('sha256', Storage::disk('local')->path($caminho)),
            'situacao' => $bloqueios === [] ? 'analisada' : 'recusada',
            'resumo' => $resultado['resumo'],
            'divergencias' => $resultado['divergencias'],
            'dados' => $bloqueios === [] ? ['cargos' => $resultado['cargos']] : null,
        ]);

        Auditoria::registrar('importacao', $importacao, "Planilha \"{$importacao->arquivo_nome}\" enviada para análise (".count($resultado['divergencias']).' divergência(s))');

        return redirect()->route('importacoes.show', $importacao);
    }

    public function show(Importacao $importacao): View
    {
        $previa = $importacao->situacao === 'analisada' ? ($importacao->dados['cargos'] ?? []) : [];

        return view('importacoes.show', ['importacao' => $importacao, 'previa' => $previa]);
    }

    public function aplicar(Importacao $importacao, AplicadorQuadroCargos $aplicador): RedirectResponse
    {
        if (! $importacao->podeAplicar()) {
            return back()->with('erro', 'Esta importação não pode mais ser aplicada.');
        }

        $r = $aplicador->aplicar($importacao);

        return redirect()->route('cargos.index', ['orgao' => $importacao->orgao_id])->with('sucesso', sprintf(
            'Quadro importado: %d cargo(s) novo(s), %d atualizado(s), %d vaga(s). O relatório de divergências continua disponível no histórico de importações.',
            $r['criados'], $r['atualizados'], $r['vagas'],
        ));
    }

    public function cancelar(Importacao $importacao): RedirectResponse
    {
        if ($importacao->situacao !== 'analisada') {
            return back()->with('erro', 'Esta importação não pode mais ser cancelada.');
        }

        $importacao->forceFill(['situacao' => 'cancelada', 'dados' => null])->save();
        Storage::disk('local')->delete($importacao->arquivo_caminho);
        Auditoria::registrar('importacao', $importacao, "Importação de \"{$importacao->arquivo_nome}\" cancelada");

        return redirect()->route('importacoes.index')->with('sucesso', 'Importação cancelada. Nada foi alterado no quadro de cargos.');
    }
}
