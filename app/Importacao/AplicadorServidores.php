<?php

namespace App\Importacao;

use App\Models\Declaracao;
use App\Models\Importacao;
use App\Models\Servidor;
use App\Models\Vaga;
use App\Servidores\OcupacaoDeVaga;
use App\Support\Auditoria;
use App\Support\Cpf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Grava os servidores de uma importação analisada.
 *
 * O servidor é localizado pelo CPF (atualiza ou cria). A vaga é escolhida no cargo
 * da planilha: primeiro a vaga em que o quadro já mostra o mesmo CPF, depois a
 * primeira vaga livre. Declarações iguais às já registradas não são duplicadas.
 * Gera um único registro de auditoria com o resumo.
 */
class AplicadorServidores
{
    public function __construct(private OcupacaoDeVaga $ocupacao) {}

    /** @return array{criados: int, atualizados: int, com_vaga: int, sem_vaga: int, declaracoes: int} */
    public function aplicar(Importacao $importacao): array
    {
        if (! $importacao->podeAplicar() || $importacao->tipo !== 'servidores') {
            throw new LogicException('Esta importação não pode ser aplicada.');
        }

        $orgaoId = $importacao->orgao_id;
        $linhas = $importacao->dados['servidores'] ?? [];

        return DB::transaction(function () use ($importacao, $orgaoId, $linhas) {
            $r = ['criados' => 0, 'atualizados' => 0, 'com_vaga' => 0, 'sem_vaga' => 0, 'declaracoes' => 0];

            Servidor::semAuditoria(fn () => Vaga::semAuditoria(fn () => Declaracao::semAuditoria(function () use ($orgaoId, $linhas, &$r) {
                $ocupadasPorCpf = $this->vagasOcupadasPorCpf($orgaoId);

                foreach ($linhas as $linha) {
                    $dados = $linha['dados'];
                    $servidor = Servidor::withoutGlobalScopes()->where('orgao_id', $orgaoId)->where('cpf_hash', Cpf::hash($dados['cpf']))->first();
                    $antes = $servidor?->getOriginal() ?? [];

                    $servidor ??= new Servidor(['orgao_id' => $orgaoId, 'situacao' => 'ativo']);
                    $r[$servidor->exists ? 'atualizados' : 'criados']++;

                    $servidor->fill($dados);
                    if ($servidor->ativo() && $linha['cargo_id'] !== null && ($servidor->vaga_id === null || $servidor->vaga?->cargo_id !== $linha['cargo_id'])) {
                        $servidor->vaga_id = $this->escolherVaga($linha['cargo_id'], $dados['cpf'], $ocupadasPorCpf);
                    }
                    $servidor->save();

                    $this->ocupacao->sincronizar($servidor->refresh(), $antes);
                    $r[$servidor->vaga_id ? 'com_vaga' : 'sem_vaga']++;

                    foreach ($linha['declaracoes'] as $d) {
                        $repetida = Declaracao::withoutGlobalScopes()->where('servidor_id', $servidor->id)->where('tipo', $d['tipo'])
                            ->whereDate('data_declaracao', $d['data_declaracao'])->where('ano_referencia', $d['ano_referencia'])->exists();

                        if (! $repetida) {
                            Declaracao::create([...$d, 'orgao_id' => $orgaoId, 'servidor_id' => $servidor->id, 'user_id' => Auth::id()]);
                            $r['declaracoes']++;
                        }
                    }
                }
            })));

            $importacao->forceFill([
                'situacao' => 'aplicada',
                'aplicada_em' => now(),
                'dados' => null, // os dados pessoais já estão no cadastro; não ficam duplicados aqui
            ])->save();

            Auditoria::registrar('importacao', $importacao, sprintf(
                'Servidores importados de "%s": %d novos, %d atualizados, %d com vaga, %d sem vaga, %d declarações',
                $importacao->arquivo_nome, $r['criados'], $r['atualizados'], $r['com_vaga'], $r['sem_vaga'], $r['declaracoes'],
            ), null, $r);

            return $r;
        });
    }

    /**
     * Vagas ocupadas no quadro sem servidor cadastrado, indexadas pelo CPF do ocupante
     * (o CPF fica criptografado, por isso a comparação é feita aqui).
     *
     * @return array<string, Vaga>
     */
    private function vagasOcupadasPorCpf(int $orgaoId): array
    {
        $mapa = [];
        Vaga::withoutGlobalScopes()->where('orgao_id', $orgaoId)->where('situacao', 'ocupada')->whereDoesntHave('servidor')
            ->each(function (Vaga $v) use (&$mapa) {
                if ($v->ocupante_cpf) {
                    $mapa[Cpf::somenteDigitos($v->ocupante_cpf)] = $v;
                }
            });

        return $mapa;
    }

    /** @param  array<string, Vaga>  $ocupadasPorCpf */
    private function escolherVaga(int $cargoId, string $cpf, array &$ocupadasPorCpf): ?int
    {
        $mesmoCpf = $ocupadasPorCpf[$cpf] ?? null;
        if ($mesmoCpf !== null && $mesmoCpf->cargo_id === $cargoId) {
            unset($ocupadasPorCpf[$cpf]);

            return $mesmoCpf->id;
        }

        return Vaga::withoutGlobalScopes()->where('cargo_id', $cargoId)->where('situacao', 'vaga')->whereDoesntHave('servidor')
            ->orderBy('numero')->value('id');
    }
}
