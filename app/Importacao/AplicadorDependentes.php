<?php

namespace App\Importacao;

use App\Models\Dependente;
use App\Models\Importacao;
use App\Support\Auditoria;
use App\Support\Cpf;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Grava os dependentes de uma importação analisada: atualiza quem já existe
 * (mesmo CPF para o mesmo servidor) e cria os demais. Não reativa encerrados.
 * Gera um único registro de auditoria com o resumo.
 */
class AplicadorDependentes
{
    /** @return array{criados: int, atualizados: int} */
    public function aplicar(Importacao $importacao): array
    {
        if (! $importacao->podeAplicar() || $importacao->tipo !== 'dependentes') {
            throw new LogicException('Esta importação não pode ser aplicada.');
        }

        return DB::transaction(function () use ($importacao) {
            $r = ['criados' => 0, 'atualizados' => 0];

            Dependente::semAuditoria(function () use ($importacao, &$r) {
                foreach ($importacao->dados['dependentes'] ?? [] as $linha) {
                    $dependente = Dependente::withoutGlobalScopes()
                        ->where('servidor_id', $linha['servidor_id'])
                        ->where('cpf_hash', Cpf::hash($linha['dados']['cpf']))
                        ->first() ?? new Dependente(['orgao_id' => $importacao->orgao_id, 'servidor_id' => $linha['servidor_id']]);

                    $r[$dependente->exists ? 'atualizados' : 'criados']++;
                    $dependente->fill($linha['dados'])->save();
                }
            });

            $importacao->forceFill(['situacao' => 'aplicada', 'aplicada_em' => now(), 'dados' => null])->save();

            Auditoria::registrar('importacao', $importacao, sprintf(
                'Dependentes importados de "%s": %d novos, %d atualizados',
                $importacao->arquivo_nome, $r['criados'], $r['atualizados'],
            ), null, $r);

            return $r;
        });
    }
}
