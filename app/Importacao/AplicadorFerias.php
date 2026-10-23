<?php

namespace App\Importacao;

use App\Ferias\Periodos;
use App\Models\Ferias;
use App\Models\Importacao;
use App\Models\PeriodoAquisitivo;
use App\Models\Servidor;
use App\Support\Auditoria;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Grava as férias de uma importação analisada: cria os períodos aquisitivos que
 * faltam (contados do ingresso), atualiza a etapa que começa no mesmo dia e cria
 * as demais. Gera um único registro de auditoria com o resumo.
 */
class AplicadorFerias
{
    public function __construct(private Periodos $periodos = new Periodos) {}

    /** @return array{criadas: int, atualizadas: int} */
    public function aplicar(Importacao $importacao): array
    {
        if (! $importacao->podeAplicar() || $importacao->tipo !== 'ferias') {
            throw new LogicException('Esta importação não pode ser aplicada.');
        }

        return DB::transaction(function () use ($importacao) {
            $r = ['criadas' => 0, 'atualizadas' => 0];
            $linhas = collect($importacao->dados['ferias'] ?? []);

            $bases = $linhas->whereNotNull('base')->pluck('base', 'servidor_id');
            foreach (Servidor::whereIn('id', $linhas->pluck('servidor_id')->unique())->get() as $servidor) {
                if (isset($bases[$servidor->id])) {
                    $this->periodos->rebasear($servidor, Carbon::parse($bases[$servidor->id]));
                }
                $this->periodos->sincronizar($servidor);
            }

            Ferias::semAuditoria(function () use ($linhas, $importacao, &$r) {
                foreach ($linhas as $linha) {
                    $periodo = PeriodoAquisitivo::where('servidor_id', $linha['servidor_id'])->whereDate('inicio', $linha['periodo_inicio'])->firstOrFail();
                    $ferias = Ferias::where('servidor_id', $linha['servidor_id'])->whereDate('inicio', $linha['dados']['inicio'])->first()
                        ?? new Ferias(['orgao_id' => $importacao->orgao_id, 'servidor_id' => $linha['servidor_id']]);

                    $r[$ferias->exists ? 'atualizadas' : 'criadas']++;
                    $ferias->fill([
                        ...$linha['dados'],
                        'periodo_aquisitivo_id' => $periodo->id,
                        'etapa' => $linha['dados']['etapa'] ?? ((int) $periodo->ferias()->max('etapa') + 1),
                        'analisado_em' => $ferias->analisado_em ?? now(),
                    ])->save();
                }
            });

            $importacao->forceFill(['situacao' => 'aplicada', 'aplicada_em' => now(), 'dados' => null])->save();

            Auditoria::registrar('importacao', $importacao, sprintf(
                'Férias importadas de "%s": %d novas, %d atualizadas',
                $importacao->arquivo_nome, $r['criadas'], $r['atualizadas'],
            ), null, $r);

            return $r;
        });
    }
}
