<?php

namespace App\Http\Controllers;

use App\Frequencia\Turnos;
use App\Models\Cargo;
use App\Models\Feriado;
use App\Models\Orgao;
use App\Models\RegraFrequencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\IpUtils;

/** Regras de frequência do órgão e o calendário de feriados e pontos facultativos. */
class RegraFrequenciaController extends Controller
{
    public function edit(Request $request): View
    {
        $orgao = $this->orgao($request);
        $ano = $request->integer('ano') ?: today()->year;

        return view('frequencia.regras', [
            'orgao' => $orgao,
            'orgaos' => $request->user()->orgao_id === null ? Orgao::where('ativo', true)->orderBy('sigla')->get() : collect(),
            'regra' => $regra = ($orgao ? RegraFrequencia::doOrgao($orgao->id) : null),
            'cargos' => $orgao ? Cargo::where('orgao_id', $orgao->id)->where('ativo', true)->orderBy('denominacao')->get(['id', 'denominacao', 'natureza']) : collect(),
            'dispensados' => $regra ? array_keys($regra->cargosDispensados()) : [],
            'ano' => $ano,
            'feriados' => $orgao ? Feriado::where('orgao_id', $orgao->id)->whereYear('data', $ano)->orderBy('data')->get() : collect(),
            'ip' => $request->ip(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $orgao = $this->orgao($request) ?? abort(404);

        $dados = $request->validate([
            'turnos_6h' => ['required', 'string', 'max:60'],
            'turnos_8h' => ['required', 'string', 'max:60'],
            'tolerancia_minutos' => ['required', 'integer', 'min:0', 'max:60'],
            'exigir_rede' => ['boolean'],
            'redes_permitidas' => ['nullable', 'required_if:exigir_rede,1', 'string', 'max:500'],
            'fundamento' => ['nullable', 'string', 'max:255'],
            'cargos_dispensados' => ['nullable', 'array'],
            'cargos_dispensados.*' => ['integer', Rule::exists('cargos', 'id')->where('orgao_id', $orgao->id)],
        ], [
            'redes_permitidas.required_if' => 'Informe as redes do órgão, ou desmarque a exigência.',
        ]);

        $erros = [];
        foreach (['turnos_6h' => 360, 'turnos_8h' => 480] as $campo => $minutos) {
            $turnos = Turnos::ler($dados[$campo]);
            if ($turnos === null) {
                $erros[$campo] = 'Horário não entendido. Escreva como 07:30-13:30 ou 08:00-12:00, 14:00-18:00.';
            } elseif (Turnos::minutos($turnos) !== $minutos) {
                $erros[$campo] = 'Esse horário soma '.Turnos::horas(Turnos::minutos($turnos)).', e a jornada é de '.($minutos / 60).'h.';
            } else {
                $dados[$campo] = $turnos;
            }
        }
        $redes = array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) ($dados['redes_permitidas'] ?? '')))));
        foreach ($redes as $rede) {
            if (! $this->redeValida($rede)) {
                $erros['redes_permitidas'] = "\"{$rede}\" não é um endereço ou faixa de rede (ex.: 10.20.0.0/16).";
            }
        }
        if ($erros !== []) {
            return back()->withErrors($erros)->withInput();
        }

        $dados['exigir_rede'] = $request->boolean('exigir_rede');
        $dados['redes_permitidas'] = $redes === [] ? null : implode(', ', $redes);
        $dados['cargos_dispensados'] = array_values(array_unique(array_map('intval', $dados['cargos_dispensados'] ?? [])));

        $regra = RegraFrequencia::where('orgao_id', $orgao->id)->first() ?? new RegraFrequencia(['orgao_id' => $orgao->id]);
        $regra->fill($dados)->save();

        return redirect()->route('frequencia.regras', $this->manter($request, $orgao))
            ->with('sucesso', 'Regras de frequência salvas. Valem para o espelho de todos os meses, inclusive os passados.');
    }

    public function incluirFeriado(Request $request): RedirectResponse
    {
        $orgao = $this->orgao($request) ?? abort(404);

        $dados = $request->validate([
            'data' => ['required', 'date_format:Y-m-d', Rule::unique('feriados')->where('orgao_id', $orgao->id)],
            'nome' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(array_keys(Feriado::TIPOS))],
        ], ['data.unique' => 'Já há um feriado ou ponto facultativo nesse dia.']);

        Feriado::create([...$dados, 'orgao_id' => $orgao->id]);

        return redirect()->route('frequencia.regras', $this->manter($request, $orgao, substr($dados['data'], 0, 4)))
            ->with('sucesso', Feriado::TIPOS[$dados['tipo']].' de '.date('d/m/Y', strtotime($dados['data'])).' incluído.');
    }

    /** Inclui os feriados nacionais e de Roraima do ano que ainda não estão no calendário. */
    public function sugerirFeriados(Request $request): RedirectResponse
    {
        $orgao = $this->orgao($request) ?? abort(404);
        $ano = (int) $request->validate(['ano' => ['required', 'integer', 'between:2000,2100']])['ano'];

        $existentes = Feriado::where('orgao_id', $orgao->id)->whereYear('data', $ano)->get()->map(fn ($f) => $f->data->format('Y-m-d'))->all();
        $novos = 0;
        foreach (Feriado::sugeridos($ano) as $data => [$nome, $tipo]) {
            if (! in_array($data, $existentes, true)) {
                Feriado::create(['orgao_id' => $orgao->id, 'data' => $data, 'nome' => $nome, 'tipo' => $tipo]);
                $novos++;
            }
        }

        return redirect()->route('frequencia.regras', $this->manter($request, $orgao, $ano))
            ->with('sucesso', $novos ? "{$novos} feriado(s) e ponto(s) facultativo(s) de {$ano} incluídos. Confira as datas com o decreto do Estado." : "O calendário de {$ano} já tinha todos os feriados sugeridos.");
    }

    public function removerFeriado(Request $request, Feriado $feriado): RedirectResponse
    {
        $orgao = $this->orgao($request) ?? abort(404);
        abort_unless($feriado->orgao_id === $orgao->id, 404);
        $feriado->delete();

        return redirect()->route('frequencia.regras', $this->manter($request, $orgao, $feriado->data->year))
            ->with('sucesso', $feriado->nome.' ('.$feriado->data->format('d/m/Y').') retirado do calendário.');
    }

    private function redeValida(string $rede): bool
    {
        [$ip, $mascara] = array_pad(explode('/', $rede, 2), 2, null);

        return filter_var($ip, FILTER_VALIDATE_IP) !== false
            && ($mascara === null || (ctype_digit($mascara) && (int) $mascara <= (str_contains($ip, ':') ? 128 : 32)))
            && IpUtils::checkIp($ip, $rede);
    }

    private function manter(Request $request, Orgao $orgao, int|string|null $ano = null): array
    {
        return array_filter(['orgao' => $request->user()->orgao_id === null ? $orgao->id : null, 'ano' => $ano]);
    }

    private function orgao(Request $request): ?Orgao
    {
        $user = $request->user();

        return $user->orgao_id !== null ? $user->orgao : Orgao::find($request->integer('orgao'));
    }
}
