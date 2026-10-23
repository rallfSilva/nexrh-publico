<?php

namespace App\Importacao;

use App\Models\Importacao;
use App\Models\RegistroPonto;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Grava as marcações de um arquivo do relógio de ponto já analisado. Marcação que
 * entrou no sistema depois da análise (mesmo servidor, mesmo minuto) é ignorada.
 * Um único registro de auditoria para o arquivo todo.
 */
class AplicadorPonto
{
    /** @return array{marcacoes: int, servidores: int} */
    public function aplicar(Importacao $importacao): array
    {
        if (! $importacao->podeAplicar() || $importacao->tipo !== 'ponto') {
            throw new LogicException('Esta importação não pode ser aplicada.');
        }

        return DB::transaction(function () use ($importacao) {
            $agora = now();
            $linhas = array_map(fn (array $m) => [
                'orgao_id' => $importacao->orgao_id,
                'servidor_id' => $m['servidor_id'],
                'momento' => $m['momento'],
                'origem' => 'relogio',
                'nsr' => $m['nsr'],
                'importacao_id' => $importacao->id,
                'registrado_por' => $importacao->user_id,
                'created_at' => $agora,
                'updated_at' => $agora,
            ], $importacao->dados['ponto'] ?? []);

            $gravadas = 0;
            foreach (array_chunk($linhas, 500) as $lote) {
                $gravadas += RegistroPonto::insertOrIgnore($lote);
            }
            $r = ['marcacoes' => $gravadas, 'servidores' => count(array_unique(array_column($linhas, 'servidor_id')))];

            $importacao->forceFill(['situacao' => 'aplicada', 'aplicada_em' => now(), 'dados' => null])->save();

            Auditoria::registrar('importacao', $importacao, sprintf(
                'Relógio de ponto importado de "%s": %d marcações de %d servidores',
                $importacao->arquivo_nome, $r['marcacoes'], $r['servidores'],
            ), null, $r);

            return $r;
        });
    }
}
