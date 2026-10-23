<?php

namespace App\Http\Controllers;

use App\Ferias\Escala;
use App\Ferias\FormularioPedido;
use App\Ferias\Marcacao;
use App\Ferias\Periodos;
use App\Models\Afastamento;
use App\Models\Ferias;
use App\Models\Servidor;
use App\Models\Unidade;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Férias, lado do RH e da chefia: escala do setor (mês e ano), fila de pedidos
 * a aprovar, marcação pelo RH, aprovação ou recusa, cancelamento e interrupção.
 */
class FeriasController extends Controller
{
    public function __construct(private Escala $escala, private Marcacao $marcacao, private FormularioPedido $formulario) {}

    public function escala(Request $request): View
    {
        $user = $request->user();
        $unidades = $this->unidadesVisiveis($request);
        $unidade = $unidades->firstWhere('id', $request->integer('unidade'))
            ?? $unidades->firstWhere('id', $user->servidor?->unidade_id)
            ?? $unidades->first();
        $visao = $request->input('visao') === 'ano' ? 'ano' : 'mes';

        $mes = preg_match('/^\d{4}-\d{2}$/', (string) $request->input('mes'))
            ? Carbon::createFromFormat('!Y-m', $request->input('mes'))
            : today()->startOfMonth();
        $ano = $request->integer('ano') ?: $mes->year;
        [$inicio, $fim] = $visao === 'ano'
            ? [Carbon::create($ano)->startOfYear(), Carbon::create($ano)->endOfYear()->startOfDay()]
            : [$mes->copy()->startOfMonth(), $mes->copy()->endOfMonth()->startOfDay()];

        $equipe = $unidade ? $this->escala->equipe($unidade) : collect();
        $ids = $equipe->pluck('id')->all();
        $ferias = Ferias::ocupam()->whereIn('servidor_id', $ids)->entre($inicio, $fim)->get();
        $afastamentos = Afastamento::validos()->whereIn('servidor_id', $ids)->entre($inicio, $fim)->get();

        return view('ferias.escala', [
            'unidades' => $unidades,
            'unidade' => $unidade,
            'visao' => $visao,
            'mes' => $mes,
            'ano' => $ano,
            'dias' => CarbonPeriod::create($inicio, $fim),
            'equipe' => $equipe,
            'grade' => $this->grade($ferias, $afastamentos, $inicio, $fim, $user),
            'proximas' => $unidade ? Ferias::with('servidor')->ocupam()->whereIn('servidor_id', $ids)
                ->where('fim', '>=', today())->where('inicio', '<=', today()->addDays(60))->orderBy('inicio')->get() : collect(),
            'aAprovar' => $user->can('aprovar-ferias') ? Ferias::aAprovarPor($request->user())->count() : 0,
        ]);
    }

    public function pendentes(Request $request): View
    {
        $pedidos = Ferias::aAprovarPor($request->user())->with(['servidor.unidade', 'periodo', 'solicitadoPor'])->orderBy('inicio')->get();

        // Conflito de agora (o setor pode ter mudado depois do pedido).
        $conflitos = $pedidos->mapWithKeys(fn (Ferias $f) => [$f->id => $this->escala->conflitos($f->servidor, $f->inicio, $f->fim, $f)]);

        return view('ferias.pendentes', ['pedidos' => $pedidos, 'conflitos' => $conflitos]);
    }

    public function create(Request $request, Servidor $servidor): View
    {
        abort_unless($servidor->ativo(), 404);

        return view('ferias.form', $this->formulario->dados($request, $servidor, false));
    }

    public function store(Request $request, Servidor $servidor): RedirectResponse
    {
        abort_unless($servidor->ativo(), 404);
        $dados = FormularioPedido::validar($request, true);

        $ferias = $this->marcacao->marcar(
            $servidor,
            $servidor->periodosAquisitivos()->findOrFail($dados['periodo_aquisitivo_id']),
            Carbon::parse($dados['inicio']),
            (int) $dados['dias'],
            $request->user(),
            false,
            ['processo_sei' => $dados['processo_sei'] ?? null, 'observacao' => $dados['observacao'] ?? null],
        );

        return redirect()->route('servidores.show', [$servidor, 'aba' => 'ferias'])
            ->with('sucesso', "Férias de {$servidor->nome} marcadas: {$ferias->periodoTexto()} ({$ferias->dias} dias).");
    }

    /** Nova data-base dos períodos (tempo de outro vínculo com o Estado), enquanto não há férias registradas. */
    public function dataBase(Request $request, Servidor $servidor, Periodos $periodos): RedirectResponse
    {
        abort_if($servidor->ferias()->exists(), 409);

        $data = $request->validate(
            ['data_base' => ['required', 'date_format:Y-m-d', 'after:1970-01-01', 'before_or_equal:today']],
            ['data_base.required' => 'Informe a data.', 'data_base.before_or_equal' => 'A data não pode ser no futuro.'],
        )['data_base'];

        $periodos->rebasear($servidor, Carbon::parse($data));

        return redirect()->route('servidores.show', [$servidor, 'aba' => 'ferias'])
            ->with('sucesso', 'Os períodos aquisitivos de '.$servidor->nome.' agora contam de '.Carbon::parse($data)->format('d/m/Y').'.');
    }

    public function sugestoes(Request $request, Servidor $servidor): JsonResponse
    {
        return response()->json($this->formulario->sugestoes($servidor, $request, false));
    }

    public function aprovar(Request $request, Servidor $servidor, Ferias $ferias): RedirectResponse
    {
        $this->podeAnalisar($request, $servidor, $ferias);

        if ($conflitos = $this->escala->conflitos($servidor, $ferias->inicio, $ferias->fim, $ferias)) {
            return $this->voltar($request, $servidor)->with('erro', 'Não dá para aprovar: '.implode(' ', $conflitos).' Recuse e peça outra data.');
        }

        $ferias->update(['situacao' => 'aprovada', 'motivo' => null, 'analisado_por' => $request->user()->id, 'analisado_em' => now()]);

        return $this->voltar($request, $servidor)->with('sucesso', "Férias de {$servidor->nome} aprovadas: {$ferias->periodoTexto()}.");
    }

    public function recusar(Request $request, Servidor $servidor, Ferias $ferias): RedirectResponse
    {
        $this->podeAnalisar($request, $servidor, $ferias);
        $motivo = $request->validate(['motivo' => ['required', 'string', 'max:255']], ['motivo.required' => 'Diga ao servidor por que o pedido foi recusado.'])['motivo'];

        $ferias->update(['situacao' => 'recusada', 'motivo' => $motivo, 'analisado_por' => $request->user()->id, 'analisado_em' => now()]);

        return $this->voltar($request, $servidor)->with('sucesso', "Pedido de férias de {$servidor->nome} recusado. O servidor vê o motivo e o saldo volta para o período.");
    }

    public function cancelar(Request $request, Servidor $servidor, Ferias $ferias): RedirectResponse
    {
        abort_unless($ferias->podeCancelar(), 409);
        $motivo = $request->validate(['motivo' => ['required', 'string', 'max:255']], ['motivo.required' => 'Informe por que as férias foram canceladas.'])['motivo'];

        $ferias->update(['situacao' => 'cancelada', 'motivo' => $motivo, 'analisado_por' => $request->user()->id, 'analisado_em' => now()]);

        return redirect()->route('servidores.show', [$servidor, 'aba' => 'ferias'])
            ->with('sucesso', "Férias de {$ferias->periodoTexto()} canceladas. Os {$ferias->dias} dias voltaram para o saldo.");
    }

    public function interromper(Request $request, Servidor $servidor, Ferias $ferias): RedirectResponse
    {
        abort_unless($ferias->podeInterromper(), 409);

        $dados = $request->validate([
            'interrompida_em' => ['required', 'date', 'after:'.$ferias->inicio->format('Y-m-d'), 'before_or_equal:'.$ferias->fim->format('Y-m-d')],
            'motivo_interrupcao' => ['required', Rule::in(Ferias::MOTIVOS_INTERRUPCAO)],
        ], [
            'interrompida_em.required' => 'Informe o dia da volta ao trabalho.',
            'interrompida_em.after' => 'A volta precisa ser depois do primeiro dia de férias. Se nem começou, cancele.',
            'interrompida_em.before_or_equal' => 'A volta precisa ser até o último dia das férias.',
            'motivo_interrupcao.required' => 'Escolha o motivo.',
            'motivo_interrupcao.in' => 'Escolha um motivo da lista.',
        ]);

        $volta = Carbon::parse($dados['interrompida_em']);
        $gozados = (int) $ferias->inicio->diffInDays($volta);
        $restantes = $ferias->dias - $gozados;

        $ferias->update([
            'situacao' => 'interrompida',
            'motivo' => $dados['motivo_interrupcao'],
            'interrompida_em' => $volta,
            'fim_previsto' => $ferias->fim,
            'fim' => $volta->copy()->subDay(),
            'dias' => $gozados,
            'analisado_por' => $request->user()->id,
            'analisado_em' => now(),
        ]);

        return redirect()->route('servidores.show', [$servidor, 'aba' => 'ferias'])
            ->with('sucesso', "Férias interrompidas: {$servidor->nome} volta em {$volta->format('d/m/Y')}. Os {$restantes} dias restantes voltaram para o saldo.");
    }

    private function podeAnalisar(Request $request, Servidor $servidor, Ferias $ferias): void
    {
        abort_if($servidor->user_id !== null && $servidor->user_id === $request->user()->id, 403, 'As próprias férias são aprovadas pela chefia superior ou pelo RH.');
        abort_unless($ferias->situacao === 'programada', 409);
    }

    private function voltar(Request $request, Servidor $servidor): RedirectResponse
    {
        return $request->input('voltar') === 'fila'
            ? redirect()->route('ferias.pendentes')
            : redirect()->route('servidores.show', [$servidor, 'aba' => 'ferias']);
    }

    /** @return Collection<int, Unidade> */
    private function unidadesVisiveis(Request $request): Collection
    {
        $user = $request->user();
        $query = Unidade::with('orgao')->where('ativo', true)->orderBy('orgao_id')->orderBy('sigla');

        return $user->can('ferias.ver') ? $query->get() : $query->whereIn('id', $user->idsUnidadesChefiadas())->get();
    }

    /**
     * Para cada servidor e dia: o que o tira do setor.
     *
     * @return array<int, array<string, array{tipo: string, titulo: string}>>
     */
    private function grade(Collection $ferias, Collection $afastamentos, Carbon $inicio, Carbon $fim, $user): array
    {
        $grade = [];

        foreach ($ferias as $f) {
            $tipo = $f->situacao === 'programada' ? 'pedido' : 'ferias';
            $titulo = ($f->situacao === 'programada' ? 'Férias aguardando aprovação' : 'Férias').' · '.$f->periodoTexto();
            foreach (CarbonPeriod::create($f->inicio->max($inicio), $f->fim->min($fim)) as $dia) {
                $grade[$f->servidor_id][$dia->format('Y-m-d')] = ['tipo' => $tipo, 'titulo' => $titulo];
            }
        }
        foreach ($afastamentos as $a) {
            $titulo = $a->tipoPara($user).' · '.$a->periodoTexto();
            foreach (CarbonPeriod::create($a->inicio->max($inicio), $a->fim->min($fim)) as $dia) {
                $grade[$a->servidor_id][$dia->format('Y-m-d')] = ['tipo' => 'afastamento', 'titulo' => $titulo];
            }
        }

        return $grade;
    }
}
