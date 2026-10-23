<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrgao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma etapa de férias (as férias de um período podem ser divididas em até 3).
 *
 * Situações gravadas: programada (aguardando aprovação), aprovada, recusada,
 * cancelada e interrompida. "Em gozo" e "gozada" saem das datas da aprovada.
 */
#[Fillable([
    'orgao_id', 'servidor_id', 'periodo_aquisitivo_id', 'etapa', 'inicio', 'fim', 'dias', 'situacao', 'processo_sei', 'observacao',
    'solicitada_pelo_servidor', 'solicitado_por', 'analisado_por', 'analisado_em', 'motivo', 'fim_previsto', 'interrompida_em',
])]
class Ferias extends Model
{
    use Auditable, BelongsToOrgao;

    protected $table = 'ferias';

    /** Situações que tiram a pessoa do setor e consomem saldo. */
    public const OCUPAM = ['programada', 'aprovada', 'interrompida'];

    /** Motivos de interrupção (Lei nº 8.112/90, art. 80, como referência provisória). */
    public const MOTIVOS_INTERRUPCAO = [
        'Necessidade do serviço declarada pela autoridade máxima do órgão',
        'Calamidade pública',
        'Comoção interna',
        'Convocação para júri',
        'Serviço militar ou eleitoral',
        'Licença ou afastamento durante as férias',
    ];

    protected function casts(): array
    {
        return [
            'inicio' => 'date',
            'fim' => 'date',
            'fim_previsto' => 'date',
            'interrompida_em' => 'date',
            'analisado_em' => 'datetime',
            'dias' => 'integer',
            'etapa' => 'integer',
            'solicitada_pelo_servidor' => 'boolean',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoAquisitivo::class, 'periodo_aquisitivo_id');
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function analisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analisado_por');
    }

    public function scopeOcupam(Builder $query): Builder
    {
        return $query->whereIn('situacao', self::OCUPAM);
    }

    /** Pedidos aguardando aprovação que o usuário pode analisar: os da equipe (ou do órgão, para o RH), nunca os próprios. */
    public function scopeAAprovarPor(Builder $query, User $usuario): Builder
    {
        return $query->where('situacao', 'programada')
            ->whereHas('servidor', fn ($q) => $q->visiveisPara($usuario)->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $usuario->id)));
    }

    /** Etapas que se sobrepõem ao intervalo. */
    public function scopeEntre(Builder $query, $inicio, $fim): Builder
    {
        return $query->where('inicio', '<=', $fim)->where('fim', '>=', $inicio);
    }

    public function ocupa(): bool
    {
        return in_array($this->situacao, self::OCUPAM, true);
    }

    /** Situação para mostrar: a aprovada vira "em gozo" ou "gozada" pelas datas. */
    public function situacaoAtual(): string
    {
        if ($this->situacao !== 'aprovada') {
            return $this->situacao;
        }

        return match (true) {
            $this->fim->lt(today()) => 'gozada',
            $this->inicio->lte(today()) => 'em_gozo',
            default => 'aprovada',
        };
    }

    /** @return array{0: string, 1: string} rótulo e classe do selo */
    public function selo(): array
    {
        return match ($this->situacaoAtual()) {
            'programada' => ['Aguardando aprovação', 'b-warn'],
            'aprovada' => ['Aprovada', 'b-info'],
            'em_gozo' => ['Em gozo', 'b-ok'],
            'gozada' => ['Gozada', 'b-mute'],
            'recusada' => ['Recusada', 'b-err'],
            'cancelada' => ['Cancelada', 'b-mute'],
            'interrompida' => ['Interrompida', 'b-warn'],
        };
    }

    public function podeCancelar(): bool
    {
        return in_array($this->situacao, ['programada', 'aprovada'], true) && $this->inicio->gt(today());
    }

    public function podeInterromper(): bool
    {
        return $this->situacao === 'aprovada' && $this->inicio->lt(today()) && $this->fim->gte(today());
    }

    public function periodoTexto(): string
    {
        return $this->inicio->format('d/m/Y').' a '.$this->fim->format('d/m/Y');
    }

    protected function generoAuditoria(): string
    {
        return 'a';
    }

    protected function rotuloAuditoria(): string
    {
        return 'Férias de '.($this->servidor?->nome ?? 'servidor #'.$this->servidor_id).' ('.$this->inicio?->format('d/m/Y').', '.$this->dias.' dias)';
    }
}
