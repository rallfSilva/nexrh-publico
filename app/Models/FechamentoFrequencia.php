<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrgao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fechamento de um mês de um setor: conferido pela chefia, fechado pelo RH (com o
 * boletim guardado) e, se preciso, reaberto pelo RH com motivo.
 */
#[Fillable([
    'orgao_id', 'unidade_id', 'mes', 'conferido_por', 'conferido_em', 'observacao_chefia',
    'fechado_por', 'fechado_em', 'boletim', 'reaberto_por', 'reaberto_em', 'motivo_reabertura',
])]
#[Hidden(['boletim'])]
class FechamentoFrequencia extends Model
{
    use Auditable, BelongsToOrgao;

    protected $table = 'fechamentos_frequencia';

    /** situação => [rótulo, classe do selo] */
    public const SITUACOES = [
        'aberto' => ['Aberto', 'b-mute'],
        'conferido' => ['Conferido pela chefia', 'b-info'],
        'fechado' => ['Fechado', 'b-ok'],
    ];

    protected function casts(): array
    {
        return [
            'mes' => 'date',
            'conferido_em' => 'datetime',
            'fechado_em' => 'datetime',
            'reaberto_em' => 'datetime',
            'boletim' => 'array',
        ];
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function conferidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conferido_por');
    }

    public function fechadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fechado_por');
    }

    public function reabertoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reaberto_por');
    }

    public function situacao(): string
    {
        return match (true) {
            $this->fechado_em !== null => 'fechado',
            $this->conferido_em !== null => 'conferido',
            default => 'aberto',
        };
    }

    public function fechado(): bool
    {
        return $this->fechado_em !== null;
    }

    /** O boletim guardado não vai para a trilha inteiro: só que mudou. */
    protected function camposProtegidosAuditoria(): array
    {
        return ['boletim'];
    }

    protected function rotuloAuditoria(): string
    {
        return 'Fechamento da frequência de '.($this->unidade?->sigla ?? 'setor #'.$this->unidade_id).' ('.$this->mes?->format('m/Y').')';
    }
}
