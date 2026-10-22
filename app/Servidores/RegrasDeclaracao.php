<?php

namespace App\Servidores;

use App\Support\Cpf;
use App\Support\FichaSegad;
use Illuminate\Validation\Rule;

/**
 * Regras das declarações da ficha (parentesco, acumulação e bens), usadas pela
 * tela e pela importação. montar() transforma os campos validados no conteúdo
 * gravado (criptografado) na declaração.
 */
class RegrasDeclaracao
{
    /** @return array<string, mixed> */
    public static function regras(string $tipo): array
    {
        $comum = ['data_declaracao' => ['required', 'date', 'after:2000-01-01', 'before_or_equal:today']];

        return $comum + match ($tipo) {
            'parentesco' => [
                'vinculo_funcional' => ['required', Rule::in(FichaSegad::VINCULOS)],
                'cargo_efetivo' => ['nullable', 'string', 'max:120'],
                'cargo_comissao' => ['required', 'string', 'max:120'],
                'possui_parente' => ['required', 'boolean'],
                'parentes' => ['exclude_unless:possui_parente,true,1', 'required', 'array', 'min:1', 'max:20'],
                'parentes.*.nome' => ['exclude_unless:possui_parente,true,1', 'required', 'string', 'max:160'],
                'parentes.*.cargo' => ['exclude_unless:possui_parente,true,1', 'required', 'string', 'max:120'],
                'parentes.*.relacao' => ['exclude_unless:possui_parente,true,1', 'required', 'string', 'max:60'],
                'parentes.*.orgao' => ['exclude_unless:possui_parente,true,1', 'required', 'string', 'max:120'],
                'possui_conjuge' => ['required', 'boolean'],
                'conjuge_nome' => ['exclude_unless:possui_conjuge,true,1', 'required', 'string', 'max:160'],
                'conjuge_cargo' => ['exclude_unless:possui_conjuge,true,1', 'required', 'string', 'max:120'],
                'conjuge_orgao' => ['exclude_unless:possui_conjuge,true,1', 'required', 'string', 'max:120'],
            ],
            'acumulacao' => [
                'acumula' => ['required', 'boolean'],
                'cargo_acumulado' => ['exclude_unless:acumula,true,1', 'required', 'string', 'max:120'],
                'instituicao' => ['exclude_unless:acumula,true,1', 'required', 'string', 'max:120'],
                'horario_trabalho' => ['exclude_unless:acumula,true,1', 'required', 'string', 'max:160'],
                'aposentado' => ['required', 'boolean'],
                'cargo_aposentadoria' => ['exclude_unless:aposentado,true,1', 'required', 'string', 'max:120'],
                'fonte_proventos' => ['exclude_unless:aposentado,true,1', 'required', 'string', 'max:120'],
            ],
            'bens' => [
                'ano_referencia' => ['required', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
                'possui_bens' => ['required', 'boolean'],
                'bens' => ['exclude_unless:possui_bens,true,1', 'required', 'array', 'min:1', 'max:50'],
                'bens.*.titular' => ['exclude_unless:possui_bens,true,1', 'required', Rule::in(array_keys(FichaSegad::TITULARES_BEM))],
                'bens.*.discriminacao' => ['exclude_unless:possui_bens,true,1', 'required', 'string', 'max:255'],
                'bens.*.valor' => ['exclude_unless:possui_bens,true,1', 'required', 'numeric', 'min:0', 'max:999999999.99'],
                'conjuge_nome' => ['nullable', 'string', 'max:160'],
                'conjuge_cpf' => ['nullable', function ($attr, $valor, $falha) {
                    if (! Cpf::valido((string) $valor)) {
                        $falha('CPF do cônjuge inválido.');
                    }
                }],
            ],
        };
    }

    /** @return array<string, string> */
    public static function mensagens(): array
    {
        return [
            'required' => 'Campo obrigatório.',
            'parentes.required' => 'Informe ao menos um parente.',
            'bens.required' => 'Informe ao menos um bem.',
            'data_declaracao.before_or_equal' => 'A data da declaração não pode ser no futuro.',
            'numeric' => 'Informe o valor em reais. Ex.: 45.000,00',
            'in' => 'Escolha uma opção da lista.',
        ];
    }

    /**
     * Junta linhas em branco, converte sim/não e valores em reais antes de validar.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public static function normalizar(array $dados): array
    {
        foreach (['possui_parente', 'possui_conjuge', 'acumula', 'aposentado', 'possui_bens'] as $campo) {
            if (isset($dados[$campo]) && ! is_bool($dados[$campo])) {
                $v = mb_strtolower(trim((string) $dados[$campo]));
                $dados[$campo] = in_array($v, ['1', 'sim', 'true'], true) ? true : (in_array($v, ['0', 'não', 'nao', 'false'], true) ? false : $dados[$campo]);
            }
        }

        foreach (['parentes', 'bens'] as $lista) {
            if (isset($dados[$lista]) && is_array($dados[$lista])) {
                $dados[$lista] = array_values(array_filter($dados[$lista], fn ($linha) => is_array($linha)
                    && array_filter(array_diff_key($linha, ['titular' => 1]), fn ($v) => trim((string) $v) !== '') !== []));
            }
        }

        foreach ($dados['bens'] ?? [] as $i => $bem) {
            $dados['bens'][$i]['valor'] = self::valor($bem['valor'] ?? null);
        }

        if (! empty($dados['conjuge_cpf'])) {
            $dados['conjuge_cpf'] = Cpf::somenteDigitos($dados['conjuge_cpf']);
        }

        return $dados;
    }

    /**
     * @param  array<string, mixed>  $validados
     * @return array<string, mixed>
     */
    public static function montar(string $tipo, array $validados): array
    {
        $conteudo = array_diff_key($validados, ['data_declaracao' => 1, 'ano_referencia' => 1]);

        foreach (['possui_parente', 'possui_conjuge', 'acumula', 'aposentado', 'possui_bens'] as $campo) {
            if (array_key_exists($campo, $conteudo)) {
                $conteudo[$campo] = (bool) $conteudo[$campo];
            }
        }

        foreach ($conteudo['bens'] ?? [] as $i => $bem) {
            $conteudo['bens'][$i]['valor'] = round((float) $bem['valor'], 2);
        }

        return $conteudo;
    }

    /** "45.000,00", "45000" ou 45000 viram 45000.0. */
    public static function valor(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $v = preg_replace('/[^\d,.-]/', '', (string) $valor);
        if (str_contains($v, ',')) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        }

        return is_numeric($v) ? (float) $v : null;
    }
}
