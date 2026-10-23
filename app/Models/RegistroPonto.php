<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrgao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma marcação de entrada ou saída: pelo próprio servidor no sistema, importada do
 * relógio de ponto (arquivo AFD) ou incluída pelo RH com motivo. Não é apagada:
 * o RH anula com motivo e ela continua no espelho, riscada.
 */
#[Fillable(['orgao_id', 'servidor_id', 'momento', 'origem', 'ip', 'registrado_por', 'motivo', 'nsr', 'importacao_id', 'anulado_em', 'anulado_por', 'motivo_anulacao'])]
class RegistroPonto extends Model
{
    use Auditable, BelongsToOrgao;

    protected $table = 'registros_ponto';

    public const ORIGENS = ['sistema' => 'Registrado no sistema', 'relogio' => 'Relógio de ponto', 'rh' => 'Incluído pelo RH'];

    protected function casts(): array
    {
        return [
            'momento' => 'datetime',
            'anulado_em' => 'datetime',
            'nsr' => 'integer',
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

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function scopeValidos(Builder $query): Builder
    {
        return $query->whereNull('anulado_em');
    }

    public function valido(): bool
    {
        return $this->anulado_em === null;
    }

    protected function camposProtegidosAuditoria(): array
    {
        return ['ip'];
    }

    protected function rotuloAuditoria(): string
    {
        return 'Registro de ponto de '.($this->servidor?->nome ?? 'servidor #'.$this->servidor_id).' em '.$this->momento?->format('d/m/Y H:i');
    }
}
