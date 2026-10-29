<?php

namespace App\Importacao;

use App\Models\Cargo;
use App\Models\Importacao;
use App\Models\Servidor;
use App\Models\Vaga;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Grava no banco o quadro lido de uma importação analisada.
 *
 * Cargos são localizados por denominação + símbolo (atualiza ou cria); as vagas de cada
 * cargo importado passam a ser exatamente as da planilha. Cargos do órgão que não
 * estão na planilha são desativados, nunca apagados. Gera um único registro de
 * auditoria com o resumo, em vez de um por vaga.
 *
 * Com $criarFichas, também cria a ficha (incompleta) de cada ocupante ainda sem cadastro.
 */
class AplicadorQuadroCargos
{
    /** @return array{criados: int, atualizados: int, desativados: int, vagas: int, desvinculados: int, fichas: ?array{criadas: int, vinculadas: int, ignoradas: list<string>}} */
    public function aplicar(Importacao $importacao, bool $criarFichas = false): array
    {
        if (! $importacao->podeAplicar()) {
            throw new LogicException('Esta importação não pode ser aplicada.');
        }

        $orgaoId = $importacao->orgao_id;
        $cargosPlanilha = $importacao->dados['cargos'] ?? [];

        return DB::transaction(function () use ($importacao, $orgaoId, $cargosPlanilha, $criarFichas) {
            $resultado = Cargo::semAuditoria(fn () => Vaga::semAuditoria(function () use ($orgaoId, $cargosPlanilha) {
                $existentes = Cargo::withoutGlobalScopes()->where('orgao_id', $orgaoId)->get()
                    ->keyBy(fn (Cargo $c) => Texto::chave($c->denominacao).'|'.Texto::chave($c->simbolo));

                $r = ['criados' => 0, 'atualizados' => 0, 'desativados' => 0, 'vagas' => 0, 'desvinculados' => 0];
                $importados = [];

                foreach ($cargosPlanilha as $dados) {
                    $chave = Texto::chave($dados['denominacao']).'|'.Texto::chave($dados['simbolo']);
                    $cargo = $existentes->get($chave) ?? new Cargo(['orgao_id' => $orgaoId]);
                    $r[$cargo->exists ? 'atualizados' : 'criados']++;

                    $cargo->fill([
                        'denominacao' => $dados['denominacao'],
                        'simbolo' => $dados['simbolo'],
                        'natureza' => $dados['natureza'],
                        'remuneracao' => $dados['remuneracao'],
                        'quantidade_vagas' => $dados['quantidade_vagas'],
                        'base_legal' => $dados['base_legal'],
                        'observacao' => $dados['observacao'] ?? $cargo->observacao,
                        'ativo' => true,
                    ])->save();

                    $importados[] = $cargo->id;
                    $numeros = [];

                    foreach ($dados['vagas'] as $v) {
                        Vaga::withoutGlobalScopes()->updateOrCreate(
                            ['cargo_id' => $cargo->id, 'numero' => $v['numero']],
                            [...$v, 'orgao_id' => $orgaoId],
                        );
                        $numeros[] = $v['numero'];
                        $r['vagas']++;
                    }

                    Vaga::withoutGlobalScopes()->where('cargo_id', $cargo->id)->whereNotIn('numero', $numeros)->delete();
                }

                // Se a planilha diz que a vaga não está mais ocupada, o servidor perde o vínculo
                // (a ficha mostra a pendência "Sem vaga vinculada").
                $r['desvinculados'] = Servidor::withoutGlobalScopes()->where('orgao_id', $orgaoId)
                    ->whereHas('vaga', fn ($q) => $q->withoutGlobalScopes()->where('situacao', '!=', 'ocupada'))
                    ->update(['vaga_id' => null]);

                $r['desativados'] = Cargo::withoutGlobalScopes()
                    ->where('orgao_id', $orgaoId)->whereNotIn('id', $importados)->where('ativo', true)
                    ->update(['ativo' => false]);

                return $r;
            }));
            $resultado['fichas'] = $criarFichas ? app(FichasDosOcupantes::class)->criar($orgaoId) : null;

            $importacao->forceFill([
                'situacao' => 'aplicada',
                'aplicada_em' => now(),
                'dados' => null, // os dados pessoais já estão nas vagas; não precisam ficar duplicados aqui
            ])->save();

            Auditoria::registrar('importacao', $importacao, sprintf(
                'Quadro de cargos importado de "%s": %d cargos criados, %d atualizados, %d desativados, %d vagas%s',
                $importacao->arquivo_nome, $resultado['criados'], $resultado['atualizados'], $resultado['desativados'], $resultado['vagas'],
                $resultado['fichas'] ? sprintf('; %d ficha(s) de servidor criada(s) e %d vinculada(s) à vaga', $resultado['fichas']['criadas'], $resultado['fichas']['vinculadas']) : '',
            ), null, $resultado);

            return $resultado;
        });
    }
}
