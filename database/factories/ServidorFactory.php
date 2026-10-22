<?php

namespace Database\Factories;

use App\Models\Orgao;
use App\Models\Servidor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Servidor fictício com todos os campos obrigatórios da ficha.
 *
 * @extends Factory<Servidor>
 */
class ServidorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'orgao_id' => Orgao::factory(),
            'situacao' => 'ativo',
            ...self::ficha(),
        ];
    }

    /** Campos da ficha (sem órgão, lotação e vaga), também usados como dados de formulário nos testes. */
    public static function ficha(array $sobrescrever = []): array
    {
        return [
            'decreto_nomeacao' => '1.388-P',
            'data_decreto' => '2026-06-24',
            'doe_numero' => '4890',
            'data_doe' => '2026-06-25',
            'efeitos_a_partir_de' => '2026-06-24',
            'carga_horaria' => '6h diárias / 30h semanais',
            'nome' => 'Servidora Fictícia '.fake()->unique()->numberBetween(1, 99999),
            'cpf' => self::cpf(),
            'sexo' => 'F',
            'estado_civil' => 2,
            'raca_cor' => 3,
            'pcd' => false,
            'tipo_deficiencia' => 99,
            'molestia_grave' => false,
            'nacionalidade' => 'Brasileira',
            'naturalidade' => 'Boa Vista/RR',
            'data_nascimento' => '1990-03-15',
            'nome_mae' => 'Mãe Fictícia da Silva',
            'pis_pasep' => '123.45678.90-1',
            'rg_numero' => '123456',
            'rg_orgao_uf' => 'SSP/RR',
            'rg_data_expedicao' => '2008-01-10',
            'titulo_numero' => '0000 1111 2222',
            'titulo_zona' => '001',
            'titulo_secao' => '0123',
            'titulo_cidade' => 'Boa Vista',
            'titulo_uf' => 'RR',
            'grau_instrucao' => 9,
            'vaga_pcd_ou_reabilitacao' => false,
            'servidor_efetivo' => false,
            'servidor_federal' => false,
            'cep' => '69300-000',
            'logradouro' => 'Rua Exemplo',
            'numero' => '100',
            'bairro' => 'Centro',
            'municipio' => 'Boa Vista',
            'uf' => 'RR',
            'celular' => '(95) 90000-0000',
            'email' => 'servidora@example.com',
            'banco_codigo' => '001',
            'banco_nome' => 'Banco do Brasil',
            'agencia' => '0000-0',
            'conta_corrente' => '00000-0',
            ...$sobrescrever,
        ];
    }

    /** CPF fictício com dígitos verificadores válidos. */
    public static function cpf(): string
    {
        do {
            $n = array_map(fn () => random_int(0, 9), range(1, 9));
            foreach ([9, 10] as $t) {
                $soma = 0;
                for ($i = 0; $i < $t; $i++) {
                    $soma += $n[$i] * ($t + 1 - $i);
                }
                $n[] = ((10 * $soma) % 11) % 10;
            }
            $cpf = implode('', $n);
        } while (preg_match('/^(\d)\1{10}$/', $cpf));

        return $cpf;
    }
}
