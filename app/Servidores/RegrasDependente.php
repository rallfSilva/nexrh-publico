<?php

namespace App\Servidores;

use App\Models\Dependente;
use App\Models\Servidor;
use App\Support\Cpf;
use App\Support\FichaSegad;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Regras do dependente, usadas pela tela e pela importação do modelo 04.
 *
 * "Universitário" sai do código de parentesco (04 e 07), para os dois nunca
 * se contradizerem. Os limites de idade de cada finalidade não bloqueiam o
 * cadastro: viram pendência na ficha (Dependente::alertas), porque a idade
 * muda com o tempo e o RH precisa ver quando passar do limite.
 */
class RegrasDependente
{
    public const LOGICOS = ['declarar_ir', 'declarar_previdencia', 'salario_familia', 'pcd', 'universitario'];

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public static function normalizar(array $dados): array
    {
        foreach ($dados as $campo => $valor) {
            if (is_string($valor)) {
                $valor = trim(preg_replace('/\s+/u', ' ', $valor));
                $dados[$campo] = $valor === '' ? null : $valor;
            }
        }

        if (isset($dados['cpf'])) {
            $dados['cpf'] = Cpf::somenteDigitos($dados['cpf']);
        }
        if (isset($dados['sexo'])) {
            $dados['sexo'] = mb_strtoupper((string) $dados['sexo']);
        }
        if (isset($dados['parentesco'])) {
            $codigo = FichaSegad::codigo(FichaSegad::PARENTESCOS, $dados['parentesco']);
            $dados['parentesco'] = $codigo === null ? $dados['parentesco'] : str_pad((string) $codigo, 2, '0', STR_PAD_LEFT);
        }

        foreach (self::LOGICOS as $campo) {
            if (array_key_exists($campo, $dados) && $dados[$campo] !== null && ! is_bool($dados[$campo])) {
                $v = mb_strtolower((string) $dados[$campo]);
                $dados[$campo] = match (true) {
                    in_array($v, ['1', 'sim', 's', 'true', 'on'], true) => true,
                    in_array($v, ['0', 'não', 'nao', 'n', 'false', 'off'], true) => false,
                    default => $dados[$campo],
                };
            }
        }

        // Universitário marcado com código de filho comum: usa o código de universitário.
        if (($dados['universitario'] ?? null) === true) {
            $dados['parentesco'] = ['03' => '04', '06' => '07'][$dados['parentesco'] ?? ''] ?? ($dados['parentesco'] ?? null);
        }
        $dados['universitario'] = in_array($dados['parentesco'] ?? null, Dependente::PARENTESCOS_UNIVERSITARIO, true);

        if (($dados['pcd'] ?? null) === false) {
            $dados['tipo_deficiencia'] = 99;
        } elseif (isset($dados['tipo_deficiencia'])) {
            $dados['tipo_deficiencia'] = FichaSegad::codigo(FichaSegad::DEFICIENCIAS, $dados['tipo_deficiencia']) ?? $dados['tipo_deficiencia'];
        }

        return $dados;
    }

    /**
     * @param  array<string, mixed>  $dados  já normalizados (as regras cruzam campos)
     * @return array<string, mixed>
     */
    public static function regras(Servidor $servidor, array $dados, ?Dependente $dependente = null): array
    {
        $filho = in_array($dados['parentesco'] ?? null, Dependente::PARENTESCOS_FILHO, true);

        return [
            'nome' => ['required', 'string', 'max:160'],
            'cpf' => ['required', self::regraCpf($servidor, $dependente)],
            'data_nascimento' => ['required', 'date', 'after:1900-01-01', 'before_or_equal:today'],
            'sexo' => ['required', Rule::in(array_keys(FichaSegad::SEXOS))],
            'parentesco' => ['required', Rule::in(self::codigosParentesco()), self::regraConjuge($servidor, $dependente)],
            'declarar_ir' => ['required', 'boolean'],
            'declarar_previdencia' => ['required', 'boolean'],
            'salario_familia' => ['required', 'boolean', Rule::prohibitedIf(($dados['salario_familia'] ?? false) === true && ! $filho)],
            'pcd' => ['required', 'boolean'],
            'tipo_deficiencia' => ['required_if:pcd,true', 'nullable', 'integer', Rule::in(array_keys(FichaSegad::DEFICIENCIAS)),
                Rule::notIn(($dados['pcd'] ?? false) === true ? [99] : [])],
            'universitario' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public static function mensagens(): array
    {
        return [
            'required' => 'Campo obrigatório.',
            'required_if' => 'Informe o tipo de deficiência.',
            'data_nascimento.before_or_equal' => 'A data de nascimento não pode ser no futuro.',
            'data_nascimento.after' => 'Confira a data de nascimento.',
            'in' => 'Escolha uma opção da lista.',
            'date' => 'Data inválida.',
            'boolean' => 'Responda sim ou não.',
            'salario_familia.prohibited' => 'O salário-família é só para filho, enteado ou equiparado.',
            'tipo_deficiencia.not_in' => 'Escolha o tipo de deficiência.',
        ];
    }

    private static function regraCpf(Servidor $servidor, ?Dependente $dependente): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falha) use ($servidor, $dependente) {
            if (! Cpf::valido((string) $valor)) {
                $falha('CPF inválido. Confira os números.');

                return;
            }
            if ($valor === $servidor->cpf) {
                $falha('Este é o CPF do próprio servidor.');

                return;
            }

            $existe = Dependente::withoutGlobalScopes()
                ->where('servidor_id', $servidor->id)
                ->where('cpf_hash', Cpf::hash((string) $valor))
                ->when($dependente?->exists, fn ($q) => $q->whereKeyNot($dependente->id))
                ->first();

            if ($existe) {
                $falha($existe->ativo()
                    ? 'Este dependente já está cadastrado para o servidor.'
                    : 'Este dependente já foi cadastrado e está encerrado. Abra o cadastro dele e reative.');
            }
        };
    }

    /** Só um cônjuge ou companheiro(a) ativo por servidor. */
    private static function regraConjuge(Servidor $servidor, ?Dependente $dependente): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falha) use ($servidor, $dependente) {
            if (! in_array($valor, Dependente::PARENTESCOS_CONJUGE, true)) {
                return;
            }

            $outro = Dependente::withoutGlobalScopes()
                ->where('servidor_id', $servidor->id)
                ->whereIn('parentesco', Dependente::PARENTESCOS_CONJUGE)
                ->whereNull('encerrado_em')
                ->when($dependente?->exists, fn ($q) => $q->whereKeyNot($dependente->id))
                ->value('nome');

            if ($outro) {
                $falha("{$outro} já está cadastrado(a) como cônjuge ou companheiro(a). Encerre aquele cadastro antes.");
            }
        };
    }

    /** @return list<string> "01", "02", ... "99" */
    public static function codigosParentesco(): array
    {
        return array_map(fn ($c) => str_pad((string) $c, 2, '0', STR_PAD_LEFT), array_keys(FichaSegad::PARENTESCOS));
    }
}
