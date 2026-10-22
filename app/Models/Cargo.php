<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrgao;
use Database\Factories\CargoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['orgao_id', 'denominacao', 'simbolo', 'natureza', 'remuneracao', 'quantidade_vagas', 'base_legal', 'observacao', 'ativo'])]
class Cargo extends Model
{
    /** @use HasFactory<CargoFactory> */
    use Auditable, BelongsToOrgao, HasFactory;

    protected $table = 'cargos';

    /** @var array<string, string> */
    public const NATUREZAS = [
        'comissao' => 'Cargo em comissão',
        'funcao_gratificada' => 'Função gratificada',
        'subsidio' => 'Subsídio',
        'efetivo' => 'Cargo efetivo',
    ];

    protected function casts(): array
    {
        return [
            'remuneracao' => 'decimal:2',
            'quantidade_vagas' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function vagas(): HasMany
    {
        return $this->hasMany(Vaga::class)->orderBy('numero');
    }

    /**
     * Totais das vagas, a partir de withCount (ou contando agora, se não vierem carregados).
     *
     * @return array{total: int, remanejadas: int, disponiveis: int, ocupadas: int, vagas: int}
     */
    public function totais(): array
    {
        $ocupadas = $this->ocupadas_count ?? $this->vagas()->where('situacao', 'ocupada')->count();
        $livres = $this->livres_count ?? $this->vagas()->where('situacao', 'vaga')->count();
        $remanejadas = $this->remanejadas_count ?? $this->vagas()->whereIn('situacao', Vaga::FORA_DO_ORGAO)->count();

        return [
            'total' => $this->quantidade_vagas,
            'remanejadas' => (int) $remanejadas,
            'disponiveis' => $this->quantidade_vagas - (int) $remanejadas,
            'ocupadas' => (int) $ocupadas,
            'vagas' => (int) $livres,
        ];
    }

    public function rotuloNatureza(): string
    {
        return self::NATUREZAS[$this->natureza] ?? $this->natureza;
    }

    protected function rotuloAuditoria(): string
    {
        return 'Cargo '.$this->denominacao.' ('.$this->simbolo.')';
    }
}
