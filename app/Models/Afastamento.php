<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrgao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Licença ou afastamento. Tira a pessoa do setor, então entra na conta do
 * mínimo de presentes. O tipo pode revelar dado de saúde: só quem vê dados
 * pessoais vê o tipo; os demais veem "Licença ou afastamento".
 * Não guarda diagnóstico nem CID.
 */
#[Fillable(['orgao_id', 'servidor_id', 'tipo', 'inicio', 'fim', 'documento', 'processo_sei', 'observacao', 'registrado_por', 'cancelado_em', 'motivo_cancelamento'])]
class Afastamento extends Model
{
    use Auditable, BelongsToOrgao;

    protected $table = 'afastamentos';

    /** Mesma lista do modelo de planilha 06. */
    public const TIPOS = [
        'Licença para tratamento de saúde',
        'Licença à gestante',
        'Licença-paternidade',
        'Licença por motivo de doença em pessoa da família',
        'Licença nojo (falecimento)',
        'Licença gala (casamento)',
        'Doação de sangue',
        'Serviço eleitoral',
        'Júri e serviços obrigatórios',
        'Capacitação',
        'Cessão a outro órgão',
        'Afastamento para mandato',
        'Outro',
    ];

    protected function casts(): array
    {
        return [
            'inicio' => 'date',
            'fim' => 'date',
            'cancelado_em' => 'datetime',
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

    public function scopeValidos(Builder $query): Builder
    {
        return $query->whereNull('cancelado_em');
    }

    public function scopeEntre(Builder $query, $inicio, $fim): Builder
    {
        return $query->where('inicio', '<=', $fim)->where('fim', '>=', $inicio);
    }

    public function valido(): bool
    {
        return $this->cancelado_em === null;
    }

    public function dias(): int
    {
        return (int) $this->inicio->diffInDays($this->fim) + 1;
    }

    /** Tipo para quem pode ver; "Licença ou afastamento" para os demais. */
    public function tipoPara(?User $usuario): string
    {
        return $usuario?->can('servidores.dados_pessoais') ? $this->tipo : 'Licença ou afastamento';
    }

    public function periodoTexto(): string
    {
        return $this->inicio->format('d/m/Y').' a '.$this->fim->format('d/m/Y');
    }

    protected function camposProtegidosAuditoria(): array
    {
        return ['tipo', 'observacao'];
    }

    protected function rotuloAuditoria(): string
    {
        return 'Afastamento de '.($this->servidor?->nome ?? 'servidor #'.$this->servidor_id);
    }
}
