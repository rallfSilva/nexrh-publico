<?php

namespace App\Relatorios;

use App\Frequencia\Fechamento;
use App\Frequencia\Turnos;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Boletim de frequência do mês, por servidor. Mês fechado sai do boletim guardado
 * no fechamento (não muda mais); mês aberto é calculado na hora e sai como "Aberto".
 */
class BoletimDeFrequencia extends Relatorio
{
    public function __construct(private Fechamento $fechamento) {}

    public function chave(): string
    {
        return 'frequencia';
    }

    public function titulo(): string
    {
        return 'Boletim de frequência';
    }

    public function descricao(): string
    {
        return 'Faltas, abonos, registros incompletos, férias, afastamentos e horas do mês, por servidor.';
    }

    public function icone(): string
    {
        return 'relogio';
    }

    public function permitido(User $user): bool
    {
        return $user->can('ver-frequencia');
    }

    public function filtros(User $user): array
    {
        return [
            'mes' => ['rotulo' => 'Mês', 'opcoes' => self::meses(), 'padrao' => today()->subMonthNoOverflow()->format('Y-m')],
            'unidade' => $this->filtroUnidade($user),
        ];
    }

    /** Os últimos 12 meses, do anterior para trás, e o mês atual (parcial). */
    public static function meses(): array
    {
        $meses = [];
        for ($i = 0; $i <= 12; $i++) {
            $m = today()->startOfMonth()->subMonthsNoOverflow($i);
            $meses[$m->format('Y-m')] = ucfirst($m->translatedFormat('F \d\e Y')).($i === 0 ? ' (em andamento)' : '');
        }

        return $meses;
    }

    public function colunas(User $user): array
    {
        return [
            'servidor' => 'Servidor', 'matricula' => 'Matrícula', 'unidade' => 'Setor', 'vinculo' => 'Vínculo',
            'dias_trabalho' => 'Dias de trabalho', 'com_registro' => 'Com registro', 'faltas' => 'Faltas', 'abonados' => 'Abonados',
            'incompletos' => 'Incompletos', 'atencao' => 'A verificar', 'ferias' => 'Férias', 'afastamentos' => 'Afastam.',
            'horas' => 'Horas / esperado', 'saldo' => 'Saldo', 'situacao' => 'Situação do mês',
        ];
    }

    public function numericas(): array
    {
        return ['dias_trabalho', 'com_registro', 'faltas', 'abonados', 'incompletos', 'atencao', 'ferias', 'afastamentos'];
    }

    public function linhas(User $user, array $filtros): Collection
    {
        $mes = Carbon::createFromFormat('!Y-m', $filtros['mes']);
        $unidades = Unidade::where('ativo', true)
            ->when(! $user->can('frequencia.ver'), fn ($q) => $q->whereIn('id', $user->idsUnidadesChefiadas()))
            ->when($filtros['unidade'] ?? null, fn ($q, $u) => $q->where('id', $u))
            ->orderBy('sigla')->get();

        return $unidades->flatMap(function (Unidade $u) use ($mes, $user) {
            $f = $this->fechamento->doMes($u, $mes);
            $linhas = $f?->fechado() ? $f->boletim : $this->fechamento->boletim($u, $mes, $user);
            $situacao = match ($f?->situacao() ?? 'aberto') {
                'fechado' => 'Fechado em '.$f->fechado_em->format('d/m/Y'),
                'conferido' => 'Conferido pela chefia',
                default => $mes->gte(today()->startOfMonth()) ? 'Em andamento' : 'Aberto',
            };

            return collect($linhas)->map(fn (array $l) => [
                'servidor' => $l['servidor'],
                'matricula' => $l['matricula'],
                'unidade' => $l['unidade'],
                'vinculo' => $l['vinculo'],
                'dias_trabalho' => $l['dias_trabalho'],
                'com_registro' => $l['com_registro'],
                'faltas' => $l['faltas'],
                'abonados' => $l['abonados'],
                'incompletos' => $l['incompletos'],
                'atencao' => $l['atencao'],
                'ferias' => $l['ferias'],
                'afastamentos' => $l['afastamentos'],
                'horas' => Turnos::horas($l['trabalhado']).' / '.Turnos::horas($l['esperado']),
                'saldo' => ($l['trabalhado'] >= $l['esperado'] ? '+' : '').Turnos::horas($l['trabalhado'] - $l['esperado']),
                'situacao' => $situacao,
            ]);
        })->values();
    }
}
