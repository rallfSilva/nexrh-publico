<?php

namespace Database\Factories;

use App\Models\Cargo;
use App\Models\Orgao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cargo>
 */
class CargoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'orgao_id' => Orgao::factory(),
            'denominacao' => 'Assessor '.fake()->unique()->word(),
            'simbolo' => 'CNES-'.fake()->randomElement(['I', 'II', 'III', 'IV']),
            'natureza' => 'comissao',
            'remuneracao' => 4472.58,
            'quantidade_vagas' => 3,
            'base_legal' => 'Lei nº 0.000, de 01/01/2026',
            'ativo' => true,
        ];
    }

    /** Cria também as vagas (todas livres). */
    public function comVagas(): static
    {
        return $this->afterCreating(function (Cargo $cargo) {
            foreach (range(1, $cargo->quantidade_vagas) as $n) {
                $cargo->vagas()->create(['orgao_id' => $cargo->orgao_id, 'numero' => $n, 'situacao' => 'vaga']);
            }
        });
    }
}
