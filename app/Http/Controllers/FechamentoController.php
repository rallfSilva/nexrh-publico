<?php

namespace App\Http\Controllers;

use App\Frequencia\Fechamento;
use App\Models\FechamentoFrequencia;
use App\Models\RegraFrequencia;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Fechamento do mês: a chefia confere o espelho da equipe e o RH fecha, setor a
 * setor, até o dia 15 do mês seguinte (ajustável nas regras). O RH reabre com motivo.
 */
class FechamentoController extends Controller
{
    public function __construct(private Fechamento $fechamento) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $mes = $this->mesPedido($request);
        $unidades = $this->unidadesVisiveis($user);
        $fechamentos = FechamentoFrequencia::with(['conferidoPor:id,name', 'fechadoPor:id,name', 'reabertoPor:id,name'])
            ->whereIn('unidade_id', $unidades->pluck('id'))->whereDate('mes', $mes)->get()->keyBy('unidade_id');

        $linhas = $unidades->map(function (Unidade $u) use ($fechamentos, $mes, $user) {
            $f = $fechamentos->get($u->id);
            $boletim = $f?->fechado() ? $f->boletim : $this->fechamento->boletim($u, $mes, $user);

            return [
                'unidade' => $u,
                'fechamento' => $f,
                'situacao' => $f?->situacao() ?? 'aberto',
                'soma' => Fechamento::somar($boletim ?? []),
                'pode_conferir' => $this->podeConferir($user, $u),
            ];
        })->filter(fn ($l) => $l['soma']['pessoas'] > 0 || $l['fechamento'])->values();

        $orgaoId = $user->orgao_id ?? $unidades->first()?->orgao_id;

        return view('frequencia.fechamento', [
            'mes' => $mes,
            'meses' => $this->meses(),
            'linhas' => $linhas,
            'prazo' => $orgaoId ? Fechamento::prazo($orgaoId, $mes) : null,
            'diaFechamento' => $orgaoId ? RegraFrequencia::doOrgao($orgaoId)->dia_fechamento : 15,
            'conferidos' => $linhas->where('situacao', 'conferido')->filter(fn ($l) => $l['soma']['em_analise'] === 0)->count(),
        ]);
    }

    public function conferir(Request $request, Unidade $unidade): RedirectResponse
    {
        abort_unless($this->podeConferir($request->user(), $unidade), 403);
        $mes = $this->mesPedido($request);
        $obs = $request->validate(['observacao_chefia' => ['nullable', 'string', 'max:500']])['observacao_chefia'] ?? null;

        $this->fechamento->conferir($unidade, $mes, $request->user(), $obs);

        return $this->voltar($mes, 'Frequência de '.$unidade->sigla.' em '.$mes->format('m/Y').' conferida. O RH fecha o mês.');
    }

    public function fechar(Request $request, Unidade $unidade): RedirectResponse
    {
        $mes = $this->mesPedido($request);
        $this->fechamento->fechar($unidade, $mes, $request->user());

        return $this->voltar($mes, 'Mês '.$mes->format('m/Y').' de '.$unidade->sigla.' fechado. O boletim ficou guardado como está agora.');
    }

    /** Fecha de uma vez os setores já conferidos pela chefia. */
    public function fecharConferidos(Request $request): RedirectResponse
    {
        $mes = $this->mesPedido($request);
        $conferidos = FechamentoFrequencia::with('unidade')->whereDate('mes', $mes)->whereNotNull('conferido_em')->whereNull('fechado_em')->get();
        $fechados = [];
        $pulados = [];
        foreach ($conferidos as $f) {
            try {
                $this->fechamento->fechar($f->unidade, $mes, $request->user());
                $fechados[] = $f->unidade->sigla;
            } catch (ValidationException) {
                $pulados[] = $f->unidade->sigla;
            }
        }

        if ($fechados === []) {
            return $this->voltar($mes, null, 'Nenhum setor conferido para fechar'.($pulados ? ' (com justificativa em análise: '.implode(', ', $pulados).').' : '.'));
        }

        return $this->voltar($mes, count($fechados).' '.(count($fechados) === 1 ? 'setor fechado' : 'setores fechados').': '.implode(', ', $fechados).'.'
            .($pulados ? ' Ficaram abertos, com justificativa em análise: '.implode(', ', $pulados).'.' : ''));
    }

    public function reabrir(Request $request, Unidade $unidade): RedirectResponse
    {
        $mes = $this->mesPedido($request);
        $motivo = $request->validate(['motivo_reabertura' => ['required', 'string', 'max:255']], ['motivo_reabertura.required' => 'Diga por que o mês está sendo reaberto.'])['motivo_reabertura'];
        $f = $this->fechamento->doMes($unidade, $mes) ?? abort(404);

        $this->fechamento->reabrir($f, $request->user(), $motivo);

        return $this->voltar($mes, 'Mês '.$mes->format('m/Y').' de '.$unidade->sigla.' reaberto. Feche de novo depois de corrigir.');
    }

    /** Mês do parâmetro ?mes=AAAA-MM; o padrão é o mês anterior. O mês atual ainda não fecha. */
    private function mesPedido(Request $request): Carbon
    {
        $mes = (string) $request->input('mes');
        $anterior = today()->startOfMonth()->subMonthNoOverflow();
        if (! preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $mes)) {
            return $anterior;
        }

        return Carbon::createFromFormat('!Y-m', $mes)->min($anterior);
    }

    /** @return array<string, string> */
    private function meses(): array
    {
        $meses = [];
        for ($i = 1; $i <= 12; $i++) {
            $m = today()->startOfMonth()->subMonthsNoOverflow($i);
            $meses[$m->format('Y-m')] = ucfirst($m->translatedFormat('F \d\e Y'));
        }

        return $meses;
    }

    private function unidadesVisiveis(User $user): Collection
    {
        return Unidade::where('ativo', true)
            ->when(! $user->can('frequencia.ver'), fn ($q) => $q->whereIn('id', $user->idsUnidadesChefiadas()))
            ->orderBy('sigla')->get();
    }

    /** Confere o RH ou quem chefia o setor. */
    private function podeConferir(User $user, Unidade $unidade): bool
    {
        return $user->can('frequencia.gerenciar') || ($user->can('frequencia.equipe') && in_array($unidade->id, array_map('intval', $user->idsUnidadesChefiadas()), true));
    }

    private function voltar(Carbon $mes, ?string $sucesso, ?string $erro = null): RedirectResponse
    {
        return redirect()->route('frequencia.fechamento', ['mes' => $mes->format('Y-m')])->with($sucesso ? 'sucesso' : 'erro', $sucesso ?? $erro);
    }
}
