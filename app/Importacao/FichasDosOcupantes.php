<?php

namespace App\Importacao;

use App\Models\Servidor;
use App\Models\Vaga;
use App\Support\Cpf;

/**
 * Cria a ficha de cada ocupante do quadro de cargos que ainda não está cadastrado,
 * com o que a planilha traz: nome, CPF, vaga e ato de nomeação. O resto da ficha
 * fica como pendência ("Ficha incompleta") até o RH preencher.
 *
 * Quem já tem ficha (mesmo CPF) não é alterado; se estiver sem vaga, passa a ocupar
 * a da planilha. CPF inválido ou repetido em duas vagas não gera ficha nova para a
 * segunda vaga: vai para a lista de ignorados, para o RH corrigir.
 */
class FichasDosOcupantes
{
    /** @return array{criadas: int, vinculadas: int, ignoradas: list<string>} */
    public function criar(int $orgaoId): array
    {
        $r = ['criadas' => 0, 'vinculadas' => 0, 'ignoradas' => []];

        $vagas = Vaga::withoutGlobalScopes()->with('cargo:id,denominacao')
            ->where('orgao_id', $orgaoId)->where('situacao', 'ocupada')
            ->orderBy('cargo_id')->orderBy('numero')->get();
        $existentes = Servidor::withoutGlobalScopes()->where('orgao_id', $orgaoId)->get()->keyBy('cpf_hash');
        $vagasComServidor = $existentes->pluck('vaga_id')->filter()->flip();
        $vistos = [];

        Servidor::semAuditoria(function () use ($orgaoId, $vagas, $existentes, $vagasComServidor, &$vistos, &$r) {
            foreach ($vagas as $vaga) {
                $rotulo = "{$vaga->cargo->denominacao} nº {$vaga->numero}";
                $cpf = Cpf::somenteDigitos($vaga->ocupante_cpf);

                if (! $vaga->ocupante_nome || ! Cpf::valido($cpf)) {
                    $r['ignoradas'][] = "{$rotulo}: CPF ausente ou inválido, a ficha não foi criada.";

                    continue;
                }
                if (isset($vistos[$cpf])) {
                    $r['ignoradas'][] = "{$rotulo}: o mesmo CPF já ocupa {$vistos[$cpf]}. A ficha ficou só na primeira vaga.";

                    continue;
                }
                $vistos[$cpf] = $rotulo;

                if ($vagasComServidor->has($vaga->id)) {
                    continue;
                }

                $servidor = $existentes->get(Cpf::hash($cpf));
                if ($servidor) {
                    if ($servidor->vaga_id === null) {
                        $servidor->update(['vaga_id' => $vaga->id]);
                        $r['vinculadas']++;
                    }

                    continue;
                }

                Servidor::create([
                    'orgao_id' => $orgaoId,
                    'vaga_id' => $vaga->id,
                    'situacao' => 'ativo',
                    'nome' => mb_substr($vaga->ocupante_nome, 0, 160),
                    'cpf' => $cpf,
                    'decreto_nomeacao' => $vaga->ato_numero ? mb_substr($vaga->ato_numero, 0, 60) : null,
                    'data_decreto' => $vaga->ato_data,
                    'efeitos_a_partir_de' => $vaga->efeito_data,
                ]);
                $r['criadas']++;
            }
        });

        return $r;
    }
}
