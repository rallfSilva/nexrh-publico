<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrgao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Declarações que acompanham a ficha da SEGAD: parentesco (Súmula Vinculante nº 13),
 * acumulação de cargos e bens (Lei nº 8.429/92, art. 13). Cada nova declaração é
 * um registro novo; as anteriores ficam como histórico.
 */
#[Fillable(['orgao_id', 'servidor_id', 'tipo', 'data_declaracao', 'ano_referencia', 'conteudo', 'user_id'])]
#[Hidden(['conteudo'])]
class Declaracao extends Model
{
    use Auditable, BelongsToOrgao;

    protected $table = 'declaracoes';

    /** @var array<string, string> */
    public const TIPOS = [
        'parentesco' => 'Declaração de grau de parentesco',
        'acumulacao' => 'Declaração de acumulação de cargos',
        'bens' => 'Declaração de bens',
    ];

    protected function casts(): array
    {
        return [
            'data_declaracao' => 'date',
            'ano_referencia' => 'integer',
            'conteudo' => 'encrypted:array',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function rotuloTipo(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    /** Resumo de uma linha, para a ficha e a lista de pendências. */
    public function resumo(): string
    {
        $c = $this->conteudo ?? [];

        return match ($this->tipo) {
            'parentesco' => match (true) {
                ! empty($c['possui_parente']) && ! empty($c['possui_conjuge']) => 'Declarou parente e cônjuge no governo',
                ! empty($c['possui_parente']) => 'Declarou '.count($c['parentes'] ?? []).' parente(s) no governo',
                ! empty($c['possui_conjuge']) => 'Declarou cônjuge no governo',
                default => 'Não possui parente nem cônjuge no governo',
            },
            'acumulacao' => match (true) {
                ! empty($c['acumula']) => 'Acumula: '.($c['cargo_acumulado'] ?? 'cargo informado').(! empty($c['instituicao']) ? ' ('.$c['instituicao'].')' : ''),
                ! empty($c['aposentado']) => 'Aposentado(a), não acumula cargo',
                default => 'Não acumula cargo, emprego ou função',
            },
            'bens' => ! empty($c['possui_bens'])
                ? count($c['bens'] ?? []).' bem(ns) declarado(s), ano '.$this->ano_referencia
                : 'Não possui bens a declarar, ano '.$this->ano_referencia,
            default => '',
        };
    }

    /** O conteúdo (nomes de parentes, bens e valores) nunca vai para a trilha. */
    protected function camposProtegidosAuditoria(): array
    {
        return ['conteudo'];
    }

    protected function generoAuditoria(): string
    {
        return 'a';
    }

    protected function rotuloAuditoria(): string
    {
        return $this->rotuloTipo().' de '.($this->servidor?->nome ?? 'servidor #'.$this->servidor_id);
    }
}
