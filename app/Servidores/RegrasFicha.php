<?php

namespace App\Servidores;

use App\Models\Servidor;
use App\Models\Unidade;
use App\Models\Vaga;
use App\Support\Cpf;
use App\Support\FichaSegad;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Regras da Ficha de Cadastro da SEGAD, usadas pela tela de cadastro e pela
 * importação da planilha modelo 03. Os campos com * na ficha são obrigatórios.
 */
class RegrasFicha
{
    /** Campos sim/não da ficha. */
    public const LOGICOS = ['pcd', 'molestia_grave', 'vaga_pcd_ou_reabilitacao', 'servidor_efetivo', 'servidor_federal'];

    /**
     * Converte o que veio da tela ou da planilha para o formato gravado:
     * CPF só com dígitos, sim/não em booleano, siglas em maiúsculas, vazios em null.
     *
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

        foreach (['sexo', 'uf', 'titulo_uf', 'ctps_uf', 'cnh_uf', 'cnh_categoria', 'tipo_sanguineo', 'rg_orgao_uf', 'reservista_orgao_uf'] as $campo) {
            if (isset($dados[$campo]) && is_string($dados[$campo])) {
                $dados[$campo] = mb_strtoupper($dados[$campo]);
            }
        }

        if (($dados['pcd'] ?? null) === false) {
            $dados['tipo_deficiencia'] = 99;
        }

        if (($dados['servidor_efetivo'] ?? null) === false) {
            $dados['matricula_efetiva'] = null;
            $dados['cargo_efetivo'] = null;
        }

        if (($dados['servidor_federal'] ?? null) === false) {
            $dados['matricula_siape'] = null;
        }

        return $dados;
    }

    /**
     * @return array<string, mixed>
     */
    public static function regras(int $orgaoId, ?Servidor $servidor = null, bool $exigeVaga = true): array
    {
        $data = ['nullable', 'date', 'after:1900-01-01', 'before:2100-01-01'];
        $ano = ['nullable', 'integer', 'min:1900', 'max:2100'];
        $uf = ['nullable', Rule::in(FichaSegad::UFS)];

        return [
            // Ato de nomeação, cargo e lotação
            'vaga_id' => [$exigeVaga ? 'required' : 'nullable', 'integer', self::regraVaga($orgaoId, $servidor)],
            'unidade_id' => ['required', 'integer', Rule::exists(Unidade::class, 'id')->where('orgao_id', $orgaoId)],
            'decreto_nomeacao' => ['required', 'string', 'max:60'],
            'data_decreto' => ['required', ...array_slice($data, 1)],
            'doe_numero' => ['nullable', 'string', 'max:20'],
            'data_doe' => $data,
            'efeitos_a_partir_de' => ['required', ...array_slice($data, 1)],
            'processo_sei' => ['nullable', 'string', 'max:40'],
            'matricula_comissionado' => ['nullable', 'string', 'max:30'],
            'carga_horaria' => ['required', 'string', 'max:40'],

            // Identificação
            'nome' => ['required', 'string', 'min:5', 'max:160'],
            'cpf' => ['required', self::regraCpf($orgaoId, $servidor)],
            'sexo' => ['required', Rule::in(array_keys(FichaSegad::SEXOS))],
            'estado_civil' => ['required', 'integer', Rule::in(array_keys(FichaSegad::ESTADOS_CIVIS))],
            'raca_cor' => ['required', 'integer', Rule::in(array_keys(FichaSegad::RACAS))],
            'pcd' => ['required', 'boolean'],
            'tipo_deficiencia' => ['required_if:pcd,true,1', 'nullable', 'integer', Rule::in(array_keys(FichaSegad::DEFICIENCIAS))],
            'molestia_grave' => ['required', 'boolean'],
            'nacionalidade' => ['required', 'string', 'max:60'],
            'naturalidade' => ['required', 'string', 'max:80'],
            'data_nascimento' => ['required', 'date', 'after:1900-01-01', 'before:-14 years'],
            'tipo_sanguineo' => ['nullable', Rule::in(FichaSegad::TIPOS_SANGUINEOS)],
            'nome_mae' => ['required', 'string', 'max:160'],
            'nome_pai' => ['nullable', 'string', 'max:160'],

            // Documentos
            'pis_pasep' => ['required', 'string', 'max:20'],
            'rg_numero' => ['required', 'string', 'max:20'],
            'rg_orgao_uf' => ['required', 'string', 'max:30'],
            'rg_data_expedicao' => ['required', ...array_slice($data, 1)],
            'reservista_numero' => ['required_if:sexo,M', 'nullable', 'string', 'max:30'],
            'reservista_orgao_uf' => ['nullable', 'string', 'max:30'],
            'reservista_data_expedicao' => $data,
            'reservista_categoria' => ['nullable', 'integer', Rule::in(array_keys(FichaSegad::CATEGORIAS_RESERVA))],
            'reservista_ano' => $ano,
            'titulo_numero' => ['required', 'string', 'max:20'],
            'titulo_zona' => ['required', 'string', 'max:10'],
            'titulo_secao' => ['required', 'string', 'max:10'],
            'titulo_data_expedicao' => $data,
            'titulo_cidade' => ['required', 'string', 'max:80'],
            'titulo_uf' => ['required', Rule::in(FichaSegad::UFS)],
            'ctps_numero' => ['nullable', 'string', 'max:20'],
            'ctps_serie' => ['nullable', 'string', 'max:20'],
            'ctps_uf' => $uf,
            'ctps_data_expedicao' => $data,
            'cnh_numero' => ['nullable', 'string', 'max:20'],
            'cnh_uf' => $uf,
            'cnh_categoria' => ['nullable', Rule::in(FichaSegad::CATEGORIAS_CNH)],
            'cnh_data_expedicao' => $data,
            'cnh_data_validade' => $data,
            'cnh_data_primeira' => $data,
            'estrangeiro_data_chegada' => $data,
            'estrangeiro_ano_chegada' => $ano,
            'estrangeiro_data_limite' => $data,
            'estrangeiro_rne' => ['nullable', 'string', 'max:20'],

            // Formação e vínculos
            'grau_instrucao' => ['required', 'integer', Rule::in(array_keys(FichaSegad::ESCOLARIDADES))],
            'curso' => ['nullable', 'string', 'max:120'],
            'instituicao_curso' => ['nullable', 'string', 'max:120'],
            'ano_inicio_primeiro_emprego' => $ano,
            'ano_fim_primeiro_emprego' => [...$ano, 'gte:ano_inicio_primeiro_emprego'],
            'vaga_pcd_ou_reabilitacao' => ['required', 'boolean'],
            'servidor_efetivo' => ['required', 'boolean'],
            'matricula_efetiva' => ['required_if:servidor_efetivo,true,1', 'nullable', 'string', 'max:30'],
            'cargo_efetivo' => ['required_if:servidor_efetivo,true,1', 'nullable', 'string', 'max:120'],
            'servidor_federal' => ['required', 'boolean'],
            'matricula_siape' => ['required_if:servidor_federal,true,1', 'nullable', 'string', 'max:30'],

            // Endereço, contato e banco
            'cep' => ['required', 'regex:/^\d{5}-?\d{3}$/'],
            'logradouro' => ['required', 'string', 'max:160'],
            'numero' => ['required', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:80'],
            'bairro' => ['required', 'string', 'max:80'],
            'municipio' => ['required', 'string', 'max:80'],
            'uf' => ['required', Rule::in(FichaSegad::UFS)],
            'celular' => ['required', 'string', 'regex:/^[\d\s()+-]{10,20}$/'],
            'email' => ['required', 'email', 'max:160'],
            'banco_codigo' => ['required', 'regex:/^\d{3}$/'],
            'banco_nome' => ['nullable', 'string', 'max:80'],
            'agencia' => ['required', 'string', 'max:10'],
            'conta_corrente' => ['required', 'string', 'max:20'],
        ];
    }

    /** Campos que a tela sempre exige, mesmo salvando a ficha pela metade. */
    public const SEMPRE_EXIGIDOS = ['nome', 'cpf'];

    /**
     * Regras para salvar a ficha pela metade: o que foi preenchido continua sendo
     * conferido (CPF válido, datas, e-mail etc.), mas os campos obrigatórios podem
     * ficar em branco. O que faltar vira a pendência "Ficha incompleta".
     *
     * @return array<string, mixed>
     */
    public static function regrasParciais(int $orgaoId, ?Servidor $servidor = null, bool $exigeVaga = true): array
    {
        $regras = self::regras($orgaoId, $servidor, $exigeVaga);

        foreach ($regras as $campo => $lista) {
            if (in_array($campo, self::SEMPRE_EXIGIDOS, true) || ($campo === 'vaga_id' && $exigeVaga)) {
                continue;
            }

            $lista = array_values(array_filter($lista, fn ($r) => ! (is_string($r) && ($r === 'required' || str_starts_with($r, 'required_if:')))));
            $regras[$campo] = in_array('nullable', $lista, true) ? $lista : ['nullable', ...$lista];
        }

        return $regras;
    }

    /** @return array<string, string> */
    public static function mensagens(): array
    {
        return [
            'required' => 'Campo obrigatório na ficha.',
            'required_if' => 'Campo obrigatório neste caso.',
            'vaga_id.required' => 'Escolha a vaga do quadro que o servidor vai ocupar.',
            'unidade_id.required' => 'Escolha a lotação.',
            'data_nascimento.before' => 'Confira a data de nascimento.',
            'cep.regex' => 'Use o formato 00000-000.',
            'celular.regex' => 'Informe o celular com DDD.',
            'banco_codigo.regex' => 'Use os 3 dígitos do código do banco. Ex.: 001',
            'ano_fim_primeiro_emprego.gte' => 'O ano de fim não pode ser antes do início.',
            'in' => 'Escolha uma opção da lista.',
            'date' => 'Data inválida.',
            'email' => 'E-mail inválido.',
        ];
    }

    /** @return array<string, string> */
    public static function nomes(): array
    {
        return [
            'nome' => 'nome', 'cpf' => 'CPF', 'nome_mae' => 'nome da mãe', 'pis_pasep' => 'PIS/PASEP',
            'rg_numero' => 'identidade', 'titulo_numero' => 'título de eleitor', 'reservista_numero' => 'reservista',
        ];
    }

    private static function regraCpf(int $orgaoId, ?Servidor $servidor): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falha) use ($orgaoId, $servidor) {
            if (! Cpf::valido((string) $valor)) {
                $falha('CPF inválido. Confira os números.');

                return;
            }

            $existe = Servidor::withoutGlobalScopes()
                ->where('orgao_id', $orgaoId)
                ->where('cpf_hash', Cpf::hash((string) $valor))
                ->when($servidor?->exists, fn ($q) => $q->whereKeyNot($servidor->id))
                ->exists();

            if ($existe) {
                $falha('Já existe um servidor com este CPF no órgão.');
            }
        };
    }

    /**
     * A vaga tem de ser do órgão, estar livre (ou ocupada só na planilha, sem
     * servidor cadastrado) ou ser a vaga atual do próprio servidor.
     */
    private static function regraVaga(int $orgaoId, ?Servidor $servidor): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falha) use ($orgaoId, $servidor) {
            $vaga = Vaga::withoutGlobalScopes()->where('orgao_id', $orgaoId)->with('servidor')->find($valor);

            if ($vaga === null) {
                $falha('Vaga não encontrada no quadro do órgão.');

                return;
            }

            if ($servidor?->exists && $servidor->vaga_id === $vaga->id) {
                return;
            }

            if (in_array($vaga->situacao, Vaga::FORA_DO_ORGAO, true)) {
                $falha('Esta vaga está remanejada ou devolvida a outro órgão.');
            } elseif ($vaga->servidor !== null) {
                $falha('Esta vaga já está com '.$vaga->servidor->nome.'.');
            }
        };
    }
}
