<?php

namespace App\Models;

use App\Frequencia\Turnos;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrgao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jornada do servidor a partir de uma data: presencial (com turnos), teletrabalho
 * ou dispensado do ponto. Uma jornada nova encerra a anterior na véspera, então o
 * espelho de meses passados continua com a jornada da época. Sem jornada gravada,
 * vale a padrão do órgão pela carga da ficha.
 */
#[Fillable(['orgao_id', 'servidor_id', 'modalidade', 'turnos', 'dias_semana', 'inicio', 'fim', 'observacao', 'registrado_por'])]
class Jornada extends Model
{
    use Auditable, BelongsToOrgao;

    protected $table = 'jornadas';

    public const MODALIDADES = [
        'presencial' => 'Presencial, com registro de ponto',
        'teletrabalho' => 'Teletrabalho, sem registro de ponto',
        'dispensado' => 'Dispensado do registro de ponto',
    ];

    /** ISO: 1 = segunda ... 7 = domingo. */
    public const DIAS = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];

    protected function casts(): array
    {
        return [
            'turnos' => 'array',
            'dias_semana' => 'array',
            'inicio' => 'date',
            'fim' => 'date',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /** Jornada padrão (não gravada) pela carga da ficha. */
    public static function padrao(Servidor $servidor, RegraFrequencia $regra): self
    {
        return new static([
            'orgao_id' => $servidor->orgao_id,
            'servidor_id' => $servidor->id,
            'modalidade' => 'presencial',
            'turnos' => $regra->turnosPara($servidor),
            'dias_semana' => [1, 2, 3, 4, 5],
            'inicio' => Carbon::create(2000),
        ]);
    }

    public function vigenteEm(Carbon $dia): bool
    {
        return $this->inicio->lte($dia) && ($this->fim === null || $this->fim->gte($dia));
    }

    public function trabalhaEm(Carbon $dia): bool
    {
        return in_array($dia->dayOfWeekIso, $this->dias_semana ?? [], true);
    }

    public function registraPonto(): bool
    {
        return $this->modalidade === 'presencial';
    }

    public function minutosPorDia(): int
    {
        return Turnos::minutos($this->turnos);
    }

    public function resumo(): string
    {
        $dias = $this->dias_semana === [1, 2, 3, 4, 5] ? 'de segunda a sexta' : implode(', ', array_map(fn ($d) => self::DIAS[$d], $this->dias_semana ?? []));

        return match ($this->modalidade) {
            'teletrabalho' => 'Teletrabalho, '.$dias,
            'dispensado' => 'Dispensado do registro de ponto',
            default => Turnos::texto($this->turnos).', '.$dias.' ('.Turnos::horas($this->minutosPorDia()).' por dia)',
        };
    }

    protected function generoAuditoria(): string
    {
        return 'a';
    }

    protected function rotuloAuditoria(): string
    {
        return 'Jornada de '.($this->servidor?->nome ?? 'servidor #'.$this->servidor_id);
    }
}
