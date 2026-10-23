<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrgao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Período aquisitivo de 12 meses, contado a partir do ingresso do servidor.
 * O saldo é o direito menos as etapas marcadas (aguardando, aprovadas ou interrompidas).
 */
#[Fillable(['orgao_id', 'servidor_id', 'inicio', 'fim', 'dias_direito'])]
class PeriodoAquisitivo extends Model
{
    use Auditable, BelongsToOrgao;

    protected $table = 'periodos_aquisitivos';

    protected function casts(): array
    {
        return [
            'inicio' => 'date',
            'fim' => 'date',
            'dias_direito' => 'integer',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function ferias(): HasMany
    {
        return $this->hasMany(Ferias::class)->orderBy('inicio');
    }

    public function diasMarcados(?Ferias $ignorar = null): int
    {
        return (int) $this->ferias
            ->whereIn('situacao', Ferias::OCUPAM)
            ->reject(fn (Ferias $f) => $ignorar !== null && $f->is($ignorar))
            ->sum('dias');
    }

    public function saldo(?Ferias $ignorar = null): int
    {
        return max(0, $this->dias_direito - $this->diasMarcados($ignorar));
    }

    /** Etapas que contam (não canceladas nem recusadas). */
    public function etapasMarcadas(?Ferias $ignorar = null): int
    {
        return $this->ferias
            ->whereIn('situacao', Ferias::OCUPAM)
            ->reject(fn (Ferias $f) => $ignorar !== null && $f->is($ignorar))
            ->count();
    }

    /** "2025/2026": como o RH costuma chamar o período. */
    public function rotulo(): string
    {
        return $this->inicio->year.'/'.$this->fim->year;
    }

    public function completo(): bool
    {
        return $this->fim->lt(today());
    }

    /** Primeiro dia em que as férias deste período podem começar. */
    public function liberadoEm(): Carbon
    {
        // O primeiro período exige 12 meses de exercício; os seguintes podem ser gozados dentro do próprio período.
        $primeiro = ! static::query()->where('servidor_id', $this->servidor_id)->where('inicio', '<', $this->inicio)->exists();

        return $primeiro ? $this->fim->copy()->addDay() : $this->inicio->copy();
    }

    protected function rotuloAuditoria(): string
    {
        return 'Período aquisitivo '.$this->rotulo().' de '.($this->servidor?->nome ?? 'servidor #'.$this->servidor_id);
    }
}
