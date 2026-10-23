<?php

namespace App\Ferias;

use App\Models\PeriodoAquisitivo;
use App\Models\RegraFerias;
use App\Models\Servidor;
use App\Support\Auditoria;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Gera os períodos aquisitivos do servidor: 12 meses cada, a partir do ingresso
 * no órgão (ou do fim do último período já cadastrado), até o período em curso.
 * Desligado não ganha período novo depois do desligamento.
 *
 * Quem traz tempo de outro vínculo com o Estado pode ter outra data-base: o RH
 * ajusta (enquanto não há férias registradas) e os períodos são refeitos.
 */
class Periodos
{
    public function sincronizar(Servidor $servidor): void
    {
        $faltam = array_filter($this->previstos($servidor), fn ($p) => ! $p['existe']);

        if ($faltam !== []) {
            $dias = RegraFerias::doOrgao($servidor->orgao_id)->dias_por_periodo;

            PeriodoAquisitivo::semAuditoria(function () use ($servidor, $faltam, $dias) {
                foreach ($faltam as $p) {
                    PeriodoAquisitivo::create([
                        'orgao_id' => $servidor->orgao_id,
                        'servidor_id' => $servidor->id,
                        'inicio' => $p['inicio'],
                        'fim' => $p['fim'],
                        'dias_direito' => $dias,
                    ]);
                }
            });
        }

        $servidor->unsetRelation('periodosAquisitivos');
    }

    /**
     * Períodos que o servidor tem ou deveria ter, sem gravar nada (a importação usa para conferir).
     *
     * @return list<array{inicio: Carbon, fim: Carbon, existe: bool}>
     */
    public function previstos(Servidor $servidor, ?Carbon $base = null): array
    {
        $existentes = $servidor->exists && $base === null
            ? PeriodoAquisitivo::where('servidor_id', $servidor->id)->orderBy('inicio')->get(['inicio', 'fim'])
            : collect();
        $lista = $existentes->map(fn ($p) => ['inicio' => $p->inicio, 'fim' => $p->fim, 'existe' => true])->all();

        $ingresso = $base ?? $servidor->ingresso();
        $limite = $servidor->ativo() ? today() : $servidor->data_desligamento;
        if ($ingresso === null || $limite === null) {
            return $lista;
        }

        $inicio = $existentes->isNotEmpty() ? $existentes->last()->fim->copy()->addDay() : $ingresso->copy()->startOfDay();
        while ($inicio->lte($limite)) {
            $fim = $inicio->copy()->addYear()->subDay();
            $lista[] = ['inicio' => $inicio->copy(), 'fim' => $fim, 'existe' => false];
            $inicio = $fim->copy()->addDay();
        }

        return $lista;
    }

    /** Refaz os períodos a partir de outra data-base. Só enquanto o servidor não tem férias registradas. */
    public function rebasear(Servidor $servidor, Carbon $base): void
    {
        if ($servidor->ferias()->exists()) {
            throw new LogicException('O servidor já tem férias registradas: os períodos não podem mudar.');
        }

        $antes = $servidor->periodosAquisitivos()->reorder()->orderBy('inicio')->value('inicio');
        PeriodoAquisitivo::semAuditoria(fn () => $servidor->periodosAquisitivos()->delete());
        $servidor->unsetRelation('periodosAquisitivos');

        $dias = RegraFerias::doOrgao($servidor->orgao_id)->dias_por_periodo;
        PeriodoAquisitivo::semAuditoria(function () use ($servidor, $base, $dias) {
            foreach ($this->previstos($servidor, $base) as $p) {
                PeriodoAquisitivo::create(['orgao_id' => $servidor->orgao_id, 'servidor_id' => $servidor->id, 'inicio' => $p['inicio'], 'fim' => $p['fim'], 'dias_direito' => $dias]);
            }
        });

        Auditoria::registrar('alterado', $servidor, 'Períodos aquisitivos de '.$servidor->nome.' passam a contar de '.$base->format('d/m/Y'), ['data_base_ferias' => $antes ? Carbon::parse($antes)->format('Y-m-d') : null], ['data_base_ferias' => $base->format('Y-m-d')]);
    }
}
