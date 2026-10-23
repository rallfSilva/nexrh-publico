<?php

namespace App\Frequencia;

use App\Models\Afastamento;
use App\Models\FechamentoFrequencia;
use App\Models\Feriado;
use App\Models\Ferias;
use App\Models\Jornada;
use App\Models\Justificativa;
use App\Models\RegistroPonto;
use App\Models\RegraFrequencia;
use App\Models\Servidor;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Espelho do ponto de um mês: para cada dia, a jornada esperada, as marcações e a
 * situação (normal, falta, registro incompleto, atraso, férias, afastamento,
 * feriado...). Férias aprovadas, afastamentos e feriados preenchem os dias sozinhos.
 * Falta, atraso e registro incompleto viram ocorrência, que o servidor justifica; o
 * dia abonado conta como trabalhado, com a jornada inteira. Em mês fechado pelo RH
 * nada mais é justificável.
 *
 * Carrega os dados de vários servidores de uma vez, para o resumo da unidade não
 * fazer uma consulta por pessoa.
 */
class Espelho
{
    /** Situações do dia: [rótulo, classe do selo]. */
    public const SITUACOES = [
        'ok' => ['Normal', 'b-ok'],
        'atencao' => ['Verificar', 'b-warn'],
        'incompleto' => ['Registro incompleto', 'b-warn'],
        'falta' => ['Sem registro', 'b-err'],
        'abonado' => ['Abonado', 'b-ok'],
        'hoje' => ['Hoje', 'b-info'],
        'futuro' => ['', ''],
        'ferias' => ['Férias', 'b-info'],
        'afastamento' => ['Licença ou afastamento', 'b-info'],
        'feriado' => ['Feriado', 'b-mute'],
        'facultativo' => ['Ponto facultativo', 'b-mute'],
        'folga' => ['Sem expediente', 'b-mute'],
        'teletrabalho' => ['Teletrabalho', 'b-mute'],
        'dispensado' => ['Dispensado do ponto', 'b-mute'],
        'fora' => ['Sem vínculo no dia', 'b-mute'],
    ];

    private RegraFrequencia $regra;

    private Carbon $inicio;

    private Carbon $fim;

    /** @var array<string, Feriado> */
    private array $feriados = [];

    /** @var array<int, Collection> */
    private array $registros = [];

    /** @var array<int, Collection> */
    private array $jornadas = [];

    /** @var array<int, Collection> */
    private array $ferias = [];

    /** @var array<int, Collection> */
    private array $afastamentos = [];

    /** @var array<int, Collection> */
    private array $justificativas = [];

    /** @var array<int, FechamentoFrequencia> fechamentos do mês, por unidade */
    private array $fechamentos = [];

    /** @param  Collection<int, Servidor>  $servidores */
    public function carregar(Collection $servidores, Carbon $mes): self
    {
        $this->inicio = $mes->copy()->startOfMonth()->startOfDay();
        $this->fim = $mes->copy()->endOfMonth()->startOfDay();
        EloquentCollection::make($servidores->all())->loadMissing('vaga:id,cargo_id'); // cargo dispensado do ponto
        $ids = $servidores->pluck('id')->all();
        $orgaoId = $servidores->first()?->orgao_id;

        $this->regra = RegraFrequencia::doOrgao((int) $orgaoId);
        $this->feriados = Feriado::where('orgao_id', $orgaoId)->whereBetween('data', [$this->inicio, $this->fim])->get()
            ->keyBy(fn (Feriado $f) => $f->data->format('Y-m-d'))->all();
        $this->registros = RegistroPonto::with(['registradoPor:id,name', 'anuladoPor:id,name'])->whereIn('servidor_id', $ids)
            ->whereBetween('momento', [$this->inicio, $this->fim->copy()->endOfDay()])->orderBy('momento')->get()
            ->groupBy('servidor_id')->all();
        $this->jornadas = Jornada::whereIn('servidor_id', $ids)->where('inicio', '<=', $this->fim)
            ->where(fn ($q) => $q->whereNull('fim')->orWhere('fim', '>=', $this->inicio))->orderByDesc('inicio')->get()
            ->groupBy('servidor_id')->all();
        $this->ferias = Ferias::whereIn('situacao', ['aprovada', 'interrompida'])->whereIn('servidor_id', $ids)
            ->entre($this->inicio, $this->fim)->get()->groupBy('servidor_id')->all();
        $this->afastamentos = Afastamento::validos()->whereIn('servidor_id', $ids)
            ->entre($this->inicio, $this->fim)->get()->groupBy('servidor_id')->all();
        $this->justificativas = Justificativa::with('analisadoPor:id,name')->whereIn('servidor_id', $ids)->where('situacao', '!=', 'retirada')
            ->entre($this->inicio, $this->fim)->latest('id')->get()->groupBy('servidor_id')->all();
        $this->fechamentos = FechamentoFrequencia::with('fechadoPor:id,name')->whereIn('unidade_id', $servidores->pluck('unidade_id')->filter()->unique()->all())
            ->whereDate('mes', $this->inicio)->get()->keyBy('unidade_id')->all();

        return $this;
    }

    public function regra(): RegraFrequencia
    {
        return $this->regra;
    }

    /** Fechamento do mês do setor do servidor (aberto, conferido ou fechado), se houver. */
    public function fechamentoDe(Servidor $servidor): ?FechamentoFrequencia
    {
        return $this->fechamentos[$servidor->unidade_id] ?? null;
    }

    /** Jornada do servidor no dia: a gravada vigente ou a padrão pela carga da ficha. */
    public function jornadaEm(Servidor $servidor, Carbon $dia): Jornada
    {
        return ($this->jornadas[$servidor->id] ?? collect())->first(fn (Jornada $j) => $j->vigenteEm($dia))
            ?? Jornada::padrao($servidor, $this->regra);
    }

    /**
     * @return array{dias: list<array<string, mixed>>, totais: array<string, int>, fechado: bool}
     */
    public function calcular(Servidor $servidor, ?User $quem = null): array
    {
        $ingresso = $servidor->ingresso()?->copy()->startOfDay();
        $saida = $servidor->ativo() ? null : $servidor->data_desligamento;
        $registros = ($this->registros[$servidor->id] ?? collect())->groupBy(fn (RegistroPonto $r) => $r->momento->format('Y-m-d'));
        $tolerancia = $this->regra->tolerancia_minutos;

        $ausencias = [];
        foreach ($this->ferias[$servidor->id] ?? [] as $f) {
            foreach (CarbonPeriod::create($f->inicio->max($this->inicio), $f->fim->min($this->fim)) as $d) {
                $ausencias[$d->format('Y-m-d')] = ['ferias', 'Férias · '.$f->periodoTexto()];
            }
        }
        foreach ($this->afastamentos[$servidor->id] ?? [] as $a) {
            foreach (CarbonPeriod::create($a->inicio->max($this->inicio), $a->fim->min($this->fim)) as $d) {
                $ausencias[$d->format('Y-m-d')] = ['afastamento', $a->tipoPara($quem).' · '.$a->periodoTexto()];
            }
        }

        $dias = [];
        $totais = ['dias_trabalho' => 0, 'trabalhados' => 0, 'faltas' => 0, 'incompletos' => 0, 'atencao' => 0, 'abonados' => 0, 'em_analise' => 0, 'a_justificar' => 0, 'ausencias' => 0, 'ferias' => 0, 'afastamentos' => 0, 'esperado' => 0, 'trabalhado' => 0];
        $justificativas = $this->justificativas[$servidor->id] ?? collect();
        $fechado = (bool) $this->fechamentoDe($servidor)?->fechado();

        foreach (CarbonPeriod::create($this->inicio, $this->fim) as $dia) {
            $chave = $dia->format('Y-m-d');
            $jornada = $this->jornadaEm($servidor, $dia);
            $doDia = $registros[$chave] ?? collect();
            $validos = $doDia->filter->valido()->values();
            $horas = $validos->map(fn (RegistroPonto $r) => $r->momento->hour * 60 + $r->momento->minute)->all();

            $trabalhado = 0;
            for ($i = 0; $i + 1 < count($horas); $i += 2) {
                $trabalhado += $horas[$i + 1] - $horas[$i];
            }

            $feriado = $this->feriados[$chave] ?? null;
            $diaDeTrabalho = $jornada->trabalhaEm($dia) && $feriado === null;
            $esperado = $diaDeTrabalho && $jornada->registraPonto() ? $jornada->minutosPorDia() : 0;
            $alertas = [];

            $situacao = match (true) {
                ($ingresso && $dia->lt($ingresso)) || ($saida && $dia->gt($saida)) => 'fora',
                isset($ausencias[$chave]) => $ausencias[$chave][0],
                $feriado !== null => $feriado->tipo === 'ponto_facultativo' ? 'facultativo' : 'feriado',
                ! $jornada->trabalhaEm($dia) => 'folga',
                $jornada->modalidade === 'teletrabalho' => 'teletrabalho',
                $jornada->modalidade === 'dispensado' => 'dispensado',
                $dia->isFuture() => 'futuro',
                $dia->isToday() => 'hoje',
                $horas === [] => 'falta',
                count($horas) % 2 === 1 => 'incompleto',
                default => 'ok',
            };

            if ($situacao === 'ok' && $jornada->turnos) {
                $entrada = Turnos::emMinutos($jornada->turnos[0][0]);
                $fimDoDia = Turnos::emMinutos($jornada->turnos[array_key_last($jornada->turnos)][1]);
                if ($horas[0] > $entrada + $tolerancia) {
                    $alertas[] = 'Entrada '.($horas[0] - $entrada).' min depois do horário';
                }
                if (end($horas) < $fimDoDia - $tolerancia) {
                    $alertas[] = 'Saída '.($fimDoDia - end($horas)).' min antes do horário';
                }
                if ($trabalhado < $esperado - $tolerancia) {
                    $alertas[] = 'Faltaram '.Turnos::horas($esperado - $trabalhado).' para a jornada';
                }
                if ($alertas !== []) {
                    $situacao = 'atencao';
                }
            }

            // Justificativa do dia: a que vale (em análise ou abonada) ou, na falta, a última recusada.
            $justificativa = null;
            if (in_array($situacao, Justificativa::OCORRENCIAS, true)) {
                $doDiaJ = $justificativas->filter(fn (Justificativa $j) => $j->inicio->lte($dia) && $j->fim->gte($dia));
                $justificativa = $doDiaJ->first(fn (Justificativa $j) => $j->situacao !== 'recusada') ?? $doDiaJ->first();
                if ($justificativa?->situacao === 'abonada') {
                    $situacao = 'abonado';
                    $trabalhado = max($trabalhado, $esperado);
                }
            }
            $justificavel = ! $fechado && in_array($situacao, Justificativa::OCORRENCIAS, true) && ! in_array($justificativa?->situacao, ['pendente', 'abonada'], true);

            if (in_array($situacao, ['ok', 'atencao', 'falta', 'incompleto', 'abonado'], true)) {
                $totais['dias_trabalho']++;
                $totais['esperado'] += $esperado;
                $totais['trabalhado'] += $trabalhado;
                $totais[match ($situacao) {
                    'falta' => 'faltas', 'incompleto' => 'incompletos', 'atencao' => 'atencao', 'abonado' => 'abonados', default => 'trabalhados'
                }]++;
                if ($situacao === 'atencao') {
                    $totais['trabalhados']++;
                }
                $totais['em_analise'] += (int) ($justificativa?->situacao === 'pendente');
                $totais['a_justificar'] += (int) $justificavel;
            } elseif (in_array($situacao, ['ferias', 'afastamento'], true) && $jornada->trabalhaEm($dia) && $feriado === null) {
                $totais['ausencias']++;
                $totais[$situacao === 'ferias' ? 'ferias' : 'afastamentos']++;
            }

            $dias[] = [
                'data' => $dia->copy(),
                'situacao' => $situacao,
                'titulo' => match ($situacao) {
                    'ferias', 'afastamento' => $ausencias[$chave][1],
                    'feriado', 'facultativo' => $feriado->nome,
                    default => self::SITUACOES[$situacao][0],
                },
                'registros' => $doDia,
                'esperado' => $esperado,
                'trabalhado' => $trabalhado,
                'alertas' => $alertas,
                'jornada' => $jornada,
                'justificativa' => $justificativa,
                'justificavel' => $justificavel,
            ];
        }

        return ['dias' => $dias, 'totais' => $totais, 'fechado' => $fechado];
    }
}
