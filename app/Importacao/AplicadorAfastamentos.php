<?php

namespace App\Importacao;

use App\Models\Afastamento;
use App\Models\Importacao;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Grava os afastamentos de uma importação analisada: atualiza o do mesmo tipo
 * que começa no mesmo dia e cria os demais. Um único registro de auditoria.
 */
class AplicadorAfastamentos
{
    /** @return array{criados: int, atualizados: int} */
    public function aplicar(Importacao $importacao): array
    {
        if (! $importacao->podeAplicar() || $importacao->tipo !== 'afastamentos') {
            throw new LogicException('Esta importação não pode ser aplicada.');
        }

        return DB::transaction(function () use ($importacao) {
            $r = ['criados' => 0, 'atualizados' => 0];

            Afastamento::semAuditoria(function () use ($importacao, &$r) {
                foreach ($importacao->dados['afastamentos'] ?? [] as $linha) {
                    $afastamento = Afastamento::where('servidor_id', $linha['servidor_id'])
                        ->where('tipo', $linha['dados']['tipo'])->whereDate('inicio', $linha['dados']['inicio'])->first()
                        ?? new Afastamento(['orgao_id' => $importacao->orgao_id, 'servidor_id' => $linha['servidor_id'], 'registrado_por' => $importacao->user_id]);

                    $r[$afastamento->exists ? 'atualizados' : 'criados']++;
                    $afastamento->fill($linha['dados'])->save();
                }
            });

            $importacao->forceFill(['situacao' => 'aplicada', 'aplicada_em' => now(), 'dados' => null])->save();

            Auditoria::registrar('importacao', $importacao, sprintf(
                'Afastamentos importados de "%s": %d novos, %d atualizados',
                $importacao->arquivo_nome, $r['criados'], $r['atualizados'],
            ), null, $r);

            return $r;
        });
    }
}
