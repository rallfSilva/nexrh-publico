<?php

namespace App\Frequencia;

use App\Models\FechamentoFrequencia;
use App\Models\Feriado;
use App\Models\RegraFrequencia;
use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Fechamento do mês por setor. A chefia confere o espelho da equipe (sem
 * justificativa em análise) e o RH fecha, guardando o boletim do mês. Mês fechado
 * não muda mais: nem marcação, nem justificativa, nem abono, nem importação do
 * relógio. O RH pode reabrir, com motivo, e fechar de novo.
 *
 * O prazo é o dia 15 do mês seguinte (ajustável nas regras), ou o dia útil depois.
 */
class Fechamento
{
    /**
     * Prazo para fechar o mês: o dia das regras do órgão (padrão 15) no mês seguinte.
     * Se cair em fim de semana ou feriado, vale o dia útil seguinte.
     */
    public static function prazo(int $orgaoId, Carbon $mes): Carbon
    {
        $dia = $mes->copy()->startOfMonth()->addMonthNoOverflow()->startOfDay()
            ->day(RegraFrequencia::doOrgao($orgaoId)->dia_fechamento);
        $feriados = Feriado::where('orgao_id', $orgaoId)->whereBetween('data', [$dia, $dia->copy()->addDays(15)])
            ->where('tipo', '!=', 'ponto_facultativo')->get()->map(fn ($f) => $f->data->format('Y-m-d'))->flip();

        while ($dia->isWeekend() || $feriados->has($dia->format('Y-m-d'))) {
            $dia->addDay();
        }

        return $dia;
    }

    /** Servidores que contam no mês do setor: os ativos e os desligados durante o mês. */
    public function servidores(Unidade $unidade, Carbon $mes): Collection
    {
        return Servidor::with('unidade:id,sigla')->where('unidade_id', $unidade->id)
            ->where(fn ($q) => $q->where('situacao', 'ativo')->orWhere('data_desligamento', '>=', $mes->copy()->startOfMonth()))
            ->orderByRaw('unaccent(nome)')->get();
    }

    /**
     * Linhas do boletim do mês, calculadas agora a partir do espelho.
     *
     * @return list<array<string, mixed>>
     */
    public function boletim(Unidade $unidade, Carbon $mes, ?User $quem = null): array
    {
        $servidores = $this->servidores($unidade, $mes);
        if ($servidores->isEmpty()) {
            return [];
        }
        $espelho = (new Espelho)->carregar($servidores, $mes);

        return $servidores->map(function (Servidor $s) use ($espelho, $quem) {
            $t = $espelho->calcular($s, $quem)['totais'];

            return [
                'servidor_id' => $s->id,
                'servidor' => $s->nome,
                'matricula' => $s->matricula(),
                'unidade' => $s->unidade?->sigla,
                'vinculo' => $s->rotuloVinculo(),
                'dias_trabalho' => $t['dias_trabalho'],
                'com_registro' => $t['trabalhados'],
                'faltas' => $t['faltas'],
                'abonados' => $t['abonados'],
                'incompletos' => $t['incompletos'],
                'atencao' => $t['atencao'],
                'em_analise' => $t['em_analise'],
                'ferias' => $t['ferias'],
                'afastamentos' => $t['afastamentos'],
                'esperado' => $t['esperado'],
                'trabalhado' => $t['trabalhado'],
            ];
        })->values()->all();
    }

    /** Somas do boletim, para o resumo do setor. */
    public static function somar(array $linhas): array
    {
        $soma = ['pessoas' => count($linhas)];
        foreach (['faltas', 'abonados', 'incompletos', 'atencao', 'em_analise', 'ferias', 'afastamentos'] as $campo) {
            $soma[$campo] = array_sum(array_column($linhas, $campo));
        }

        return $soma;
    }

    public function doMes(Unidade $unidade, Carbon $mes): ?FechamentoFrequencia
    {
        return FechamentoFrequencia::where('unidade_id', $unidade->id)->whereDate('mes', $mes->copy()->startOfMonth())->first();
    }

    /** A chefia do setor (ou o RH) diz que conferiu o espelho da equipe. */
    public function conferir(Unidade $unidade, Carbon $mes, User $quem, ?string $observacao): FechamentoFrequencia
    {
        $this->exigirMesEncerrado($mes);
        $fechamento = $this->doMes($unidade, $mes);
        if ($fechamento?->fechado()) {
            throw ValidationException::withMessages(['fechamento' => 'O mês de '.$unidade->sigla.' já está fechado pelo RH.']);
        }
        $this->exigirSemAnalise($unidade, $mes, $quem);

        $fechamento = $this->registro($unidade, $mes, $fechamento)->fill([
            'conferido_por' => $quem->id,
            'conferido_em' => now(),
            'observacao_chefia' => $observacao,
        ]);
        $fechamento->save();

        return $fechamento;
    }

    /** O RH fecha o mês do setor e guarda o boletim como ficou. */
    public function fechar(Unidade $unidade, Carbon $mes, User $quem): FechamentoFrequencia
    {
        $this->exigirMesEncerrado($mes);
        $fechamento = $this->doMes($unidade, $mes);
        if ($fechamento?->fechado()) {
            throw ValidationException::withMessages(['fechamento' => 'O mês de '.$unidade->sigla.' já está fechado.']);
        }
        $this->exigirSemAnalise($unidade, $mes, $quem);

        $fechamento = $this->registro($unidade, $mes, $fechamento)->fill([
            'fechado_por' => $quem->id,
            'fechado_em' => now(),
            'boletim' => $this->boletim($unidade, $mes),
        ]);
        $fechamento->save();

        return $fechamento;
    }

    /** O RH reabre um mês fechado, com motivo. A conferência da chefia continua valendo. */
    public function reabrir(FechamentoFrequencia $fechamento, User $quem, string $motivo): FechamentoFrequencia
    {
        if (! $fechamento->fechado()) {
            throw ValidationException::withMessages(['fechamento' => 'Esse mês não está fechado.']);
        }

        $fechamento->update([
            'fechado_por' => null,
            'fechado_em' => null,
            'boletim' => null,
            'reaberto_por' => $quem->id,
            'reaberto_em' => now(),
            'motivo_reabertura' => $motivo,
        ]);

        return $fechamento;
    }

    /** O dia está num mês fechado do setor do servidor? */
    public static function estaFechado(Servidor $servidor, Carbon $dia): bool
    {
        return $servidor->unidade_id !== null && FechamentoFrequencia::where('unidade_id', $servidor->unidade_id)
            ->whereDate('mes', $dia->copy()->startOfMonth())->whereNotNull('fechado_em')->exists();
    }

    /** Algum mês fechado entre as datas, para o servidor? Devolve o primeiro, em texto (mm/aaaa). */
    public static function primeiroFechadoEntre(Servidor $servidor, Carbon $inicio, Carbon $fim): ?string
    {
        if ($servidor->unidade_id === null) {
            return null;
        }

        return FechamentoFrequencia::where('unidade_id', $servidor->unidade_id)->whereNotNull('fechado_em')
            ->whereBetween('mes', [$inicio->copy()->startOfMonth(), $fim->copy()->startOfMonth()])
            ->orderBy('mes')->first()?->mes->format('m/Y');
    }

    /**
     * Meses fechados de vários servidores de uma vez, pelo setor de cada um.
     *
     * @param  array<int, int|null>  $unidadePorServidor  servidor_id => unidade_id
     * @return array<string, true> "servidor_id|AAAA-MM" => true
     */
    public static function mesesFechados(array $unidadePorServidor): array
    {
        $fechados = FechamentoFrequencia::whereIn('unidade_id', array_filter(array_unique($unidadePorServidor)))
            ->whereNotNull('fechado_em')->get(['unidade_id', 'mes'])
            ->groupBy('unidade_id')->map(fn ($g) => $g->map(fn ($f) => $f->mes->format('Y-m'))->all());

        $saida = [];
        foreach ($unidadePorServidor as $servidorId => $unidadeId) {
            foreach ($fechados[$unidadeId] ?? [] as $mes) {
                $saida[$servidorId.'|'.$mes] = true;
            }
        }

        return $saida;
    }

    /** Erro de validação padrão para mudanças em mês fechado. */
    public static function erroMesFechado(string $campo, string $mes): ValidationException
    {
        return ValidationException::withMessages([$campo => 'O mês '.$mes.' já foi fechado pelo RH. Para mudar, o RH precisa reabrir o mês do setor.']);
    }

    private function registro(Unidade $unidade, Carbon $mes, ?FechamentoFrequencia $fechamento): FechamentoFrequencia
    {
        return $fechamento ?? new FechamentoFrequencia([
            'orgao_id' => $unidade->orgao_id,
            'unidade_id' => $unidade->id,
            'mes' => $mes->copy()->startOfMonth(),
        ]);
    }

    private function exigirMesEncerrado(Carbon $mes): void
    {
        if ($mes->copy()->startOfMonth()->gte(today()->startOfMonth())) {
            throw ValidationException::withMessages(['fechamento' => 'Só dá para conferir e fechar depois que o mês terminar.']);
        }
    }

    private function exigirSemAnalise(Unidade $unidade, Carbon $mes, User $quem): void
    {
        $emAnalise = array_sum(array_column($this->boletim($unidade, $mes, $quem), 'em_analise'));
        if ($emAnalise > 0) {
            throw ValidationException::withMessages(['fechamento' => $unidade->sigla.' tem '.$emAnalise.' '.($emAnalise === 1 ? 'dia com justificativa em análise' : 'dias com justificativa em análise').'. Abone ou recuse antes de conferir.']);
        }
    }
}
