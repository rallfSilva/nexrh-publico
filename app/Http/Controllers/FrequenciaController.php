<?php

namespace App\Http\Controllers;

use App\Frequencia\Espelho;
use App\Frequencia\Turnos;
use App\Models\Jornada;
use App\Models\RegistroPonto;
use App\Models\Servidor;
use App\Models\Unidade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Frequência, lado do RH e da chefia: resumo do mês por unidade, espelho de cada
 * servidor e, para o RH, jornada, inclusão de marcação esquecida e anulação.
 */
class FrequenciaController extends Controller
{
    /** Mês do parâmetro ?mes=AAAA-MM, ou o mês atual. */
    public static function mesPedido(Request $request): Carbon
    {
        $mes = (string) $request->input('mes');

        return preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $mes) ? Carbon::createFromFormat('!Y-m', $mes) : today()->startOfMonth();
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $unidades = $this->unidadesVisiveis($request);
        $unidade = $unidades->firstWhere('id', $request->integer('unidade'))
            ?? $unidades->firstWhere('id', $user->servidor?->unidade_id)
            ?? $unidades->first();
        $mes = self::mesPedido($request);

        $equipe = $unidade ? Servidor::where('unidade_id', $unidade->id)
            ->where(fn ($q) => $q->where('situacao', 'ativo')->orWhere('data_desligamento', '>=', $mes->copy()->startOfMonth()))
            ->orderBy('nome')->get() : collect();

        $linhas = collect();
        if ($equipe->isNotEmpty()) {
            $espelho = (new Espelho)->carregar($equipe, $mes);
            $linhas = $equipe->map(function (Servidor $s) use ($espelho, $user, $mes) {
                $calculo = $espelho->calcular($s, $user);
                $hoje = collect($calculo['dias'])->first(fn ($d) => $d['data']->isToday());

                return ['servidor' => $s, 'totais' => $calculo['totais'], 'hoje' => $hoje, 'jornada' => $espelho->jornadaEm($s, today()->min($mes->copy()->endOfMonth()))];
            });
        }

        return view('frequencia.index', [
            'unidades' => $unidades,
            'unidade' => $unidade,
            'mes' => $mes,
            'linhas' => $linhas,
        ]);
    }

    public function espelho(Request $request, Servidor $servidor): View
    {
        $mes = self::mesPedido($request);
        $espelho = (new Espelho)->carregar(collect([$servidor]), $mes);

        return view('frequencia.espelho', [
            'servidor' => $servidor->load('unidade'),
            'mes' => $mes,
            'calculo' => $espelho->calcular($servidor, $request->user()),
            'jornadaAtual' => $espelho->jornadaEm($servidor, today()),
            'jornadas' => $servidor->jornadas()->with('registradoPor:id,name')->get(),
            'regra' => $espelho->regra(),
        ]);
    }

    /** Marcação esquecida ou feita fora do sistema, incluída pelo RH com motivo. */
    public function incluir(Request $request, Servidor $servidor): RedirectResponse
    {
        $dados = $request->validate([
            'data' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.($servidor->ingresso()?->format('Y-m-d') ?? '2000-01-01')],
            'hora' => ['required', 'date_format:H:i'],
            'motivo' => ['required', 'string', 'max:255'],
        ], [
            'data.before_or_equal' => 'Não dá para incluir marcação no futuro.',
            'data.after_or_equal' => 'A data é antes do ingresso do servidor.',
            'motivo.required' => 'Diga por que a marcação está sendo incluída (ex.: esqueceu de registrar, sistema fora do ar).',
        ]);

        $momento = Carbon::parse($dados['data'].' '.$dados['hora']);
        if ($momento->isFuture()) {
            return back()->withErrors(['hora' => 'Não dá para incluir marcação no futuro.'])->withInput();
        }
        if (RegistroPonto::where('servidor_id', $servidor->id)->where('momento', $momento)->exists()) {
            return back()->withErrors(['hora' => 'Já existe uma marcação nesse horário.'])->withInput();
        }

        RegistroPonto::create([
            'orgao_id' => $servidor->orgao_id,
            'servidor_id' => $servidor->id,
            'momento' => $momento,
            'origem' => 'rh',
            'ip' => $request->ip(),
            'registrado_por' => $request->user()->id,
            'motivo' => $dados['motivo'],
        ]);

        return redirect()->route('frequencia.espelho', [$servidor, 'mes' => $momento->format('Y-m')])
            ->with('sucesso', 'Marcação de '.$momento->format('d/m/Y \à\s H:i').' incluída no espelho de '.$servidor->nome.'.');
    }

    /** Anula uma marcação: ela continua no espelho, riscada, com o motivo. */
    public function anular(Request $request, Servidor $servidor, RegistroPonto $registro): RedirectResponse
    {
        abort_unless($registro->servidor_id === $servidor->id, 404);
        abort_unless($registro->valido(), 409);
        $motivo = $request->validate(['motivo_anulacao' => ['required', 'string', 'max:255']], ['motivo_anulacao.required' => 'Diga por que a marcação está sendo anulada.'])['motivo_anulacao'];

        $registro->update(['anulado_em' => now(), 'anulado_por' => $request->user()->id, 'motivo_anulacao' => $motivo]);

        return redirect()->route('frequencia.espelho', [$servidor, 'mes' => $registro->momento->format('Y-m')])
            ->with('sucesso', 'Marcação de '.$registro->momento->format('d/m/Y \à\s H:i').' anulada. Ela continua no espelho, riscada, com o motivo.');
    }

    /** Nova jornada a partir de uma data. A anterior termina na véspera; meses passados não mudam. */
    public function jornada(Request $request, Servidor $servidor): RedirectResponse
    {
        $ultima = $servidor->jornadas()->first();
        $dados = $request->validate([
            'modalidade' => ['required', Rule::in(array_keys(Jornada::MODALIDADES))],
            'turnos' => ['nullable', 'required_if:modalidade,presencial', 'string', 'max:60'],
            'dias_semana' => ['required', 'array', 'min:1'],
            'dias_semana.*' => ['integer', 'between:1,7'],
            'inicio' => ['required', 'date_format:Y-m-d', ...($ultima ? ['after:'.$ultima->inicio->format('Y-m-d')] : [])],
            'observacao' => ['nullable', 'string', 'max:255'],
        ], [
            'turnos.required_if' => 'Informe o horário, ex.: 07:30-13:30 ou 08:00-12:00, 14:00-18:00.',
            'dias_semana.required' => 'Marque pelo menos um dia da semana.',
            'inicio.after' => 'A nova jornada precisa começar depois de '.$ultima?->inicio->format('d/m/Y').', início da jornada atual.',
        ]);

        $turnos = null;
        if ($dados['modalidade'] === 'presencial') {
            $turnos = Turnos::ler($dados['turnos']);
            if ($turnos === null) {
                return back()->withErrors(['turnos' => 'Horário não entendido. Escreva como 07:30-13:30 ou 08:00-12:00, 14:00-18:00.'])->withInput();
            }
        }

        $inicio = Carbon::parse($dados['inicio']);
        $dias = array_values(array_unique(array_map('intval', $dados['dias_semana'])));
        sort($dias);

        DB::transaction(function () use ($servidor, $ultima, $inicio, $dados, $turnos, $dias, $request) {
            if ($ultima && ($ultima->fim === null || $ultima->fim->gte($inicio))) {
                $ultima->update(['fim' => $inicio->copy()->subDay()]);
            }

            Jornada::create([
                'orgao_id' => $servidor->orgao_id,
                'servidor_id' => $servidor->id,
                'modalidade' => $dados['modalidade'],
                'turnos' => $turnos,
                'dias_semana' => $dias,
                'inicio' => $inicio,
                'observacao' => $dados['observacao'] ?? null,
                'registrado_por' => $request->user()->id,
            ]);
        });

        return redirect()->route('frequencia.espelho', [$servidor, 'mes' => $request->input('mes')])
            ->with('sucesso', 'Jornada de '.$servidor->nome.' alterada a partir de '.$inicio->format('d/m/Y').'.');
    }

    private function unidadesVisiveis(Request $request): Collection
    {
        $user = $request->user();
        $query = Unidade::with('orgao')->where('ativo', true)->orderBy('orgao_id')->orderBy('sigla');

        return $user->can('frequencia.ver') ? $query->get() : $query->whereIn('id', $user->idsUnidadesChefiadas())->get();
    }
}
