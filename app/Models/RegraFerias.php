<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrgao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Regras de férias do órgão, ajustáveis pelo RH. Enquanto o órgão não grava as
 * suas, valem os valores provisórios (30 dias, até 3 etapas de 10 dias ou mais,
 * 2 períodos acumulados e 30 dias de antecedência no pedido).
 */
#[Fillable(['orgao_id', 'dias_por_periodo', 'max_etapas', 'min_dias_etapa', 'max_periodos_acumulados', 'antecedencia_dias', 'fundamento'])]
class RegraFerias extends Model
{
    use Auditable, BelongsToOrgao;

    protected $table = 'regras_ferias';

    public const PADRAO = [
        'dias_por_periodo' => 30,
        'max_etapas' => 3,
        'min_dias_etapa' => 10,
        'max_periodos_acumulados' => 2,
        'antecedencia_dias' => 30,
        'fundamento' => 'Regras provisórias, a confirmar com a norma de férias do órgão.',
    ];

    protected function casts(): array
    {
        return [
            'dias_por_periodo' => 'integer',
            'max_etapas' => 'integer',
            'min_dias_etapa' => 'integer',
            'max_periodos_acumulados' => 'integer',
            'antecedencia_dias' => 'integer',
        ];
    }

    /** Regras gravadas do órgão ou, na falta, as provisórias (sem gravar). */
    public static function doOrgao(int $orgaoId): self
    {
        return static::where('orgao_id', $orgaoId)->first()
            ?? new static(['orgao_id' => $orgaoId, ...self::PADRAO]);
    }

    protected function generoAuditoria(): string
    {
        return 'a';
    }

    protected function rotuloAuditoria(): string
    {
        return 'Regras de férias do órgão';
    }
}
