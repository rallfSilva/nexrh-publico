<?php

namespace App\Http\Controllers;

use App\Models\Orgao;
use App\Models\RegraFerias;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Regras de férias do órgão, ajustáveis pelo RH quando a norma for confirmada. */
class RegraFeriasController extends Controller
{
    public function edit(Request $request): View
    {
        $orgao = $this->orgao($request);

        return view('ferias.regras', [
            'orgao' => $orgao,
            'orgaos' => $request->user()->orgao_id === null ? Orgao::where('ativo', true)->orderBy('sigla')->get() : collect(),
            'regra' => $orgao ? RegraFerias::doOrgao($orgao->id) : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $orgao = $this->orgao($request) ?? abort(404);

        $dados = $request->validate([
            'dias_por_periodo' => ['required', 'integer', 'min:1', 'max:60'],
            'max_etapas' => ['required', 'integer', 'min:1', 'max:6'],
            'min_dias_etapa' => ['required', 'integer', 'min:1', 'lte:dias_por_periodo'],
            'max_periodos_acumulados' => ['required', 'integer', 'min:1', 'max:5'],
            'antecedencia_dias' => ['required', 'integer', 'min:0', 'max:180'],
            'fundamento' => ['nullable', 'string', 'max:255'],
        ], [
            'min_dias_etapa.lte' => 'O mínimo por etapa não pode passar dos dias do período.',
        ]);

        $regra = RegraFerias::where('orgao_id', $orgao->id)->first() ?? new RegraFerias(['orgao_id' => $orgao->id]);
        $regra->fill($dados)->save();

        return redirect()->route('ferias.regras', $request->user()->orgao_id === null ? ['orgao' => $orgao->id] : [])
            ->with('sucesso', 'Regras de férias salvas. Valem para os próximos pedidos e marcações; o que já foi marcado continua como está.');
    }

    private function orgao(Request $request): ?Orgao
    {
        $user = $request->user();

        return $user->orgao_id !== null ? $user->orgao : Orgao::find($request->integer('orgao'));
    }
}
