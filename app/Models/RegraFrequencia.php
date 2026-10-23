<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrgao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Regras de frequência do órgão, ajustáveis pelo RH. Enquanto o órgão não grava
 * as suas, valem as provisórias: o horário sai da carga da ficha (6h corridas das
 * 7h30 às 13h30, ou 8h das 8h às 12h e das 14h às 18h), 10 minutos de tolerância
 * e registro só de dentro da rede do órgão.
 */
#[Fillable(['orgao_id', 'turnos_6h', 'turnos_8h', 'tolerancia_minutos', 'exigir_rede', 'redes_permitidas', 'fundamento'])]
class RegraFrequencia extends Model
{
    use Auditable, BelongsToOrgao;

    protected $table = 'regras_frequencia';

    /** Redes privadas: em instalação local ou na rede interna, só quem está dentro do órgão chega a elas. */
    public const REDES_PADRAO = '10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 127.0.0.0/8, ::1';

    public const PADRAO = [
        'turnos_6h' => [['07:30', '13:30']],
        'turnos_8h' => [['08:00', '12:00'], ['14:00', '18:00']],
        'tolerancia_minutos' => 10,
        'exigir_rede' => true,
        'redes_permitidas' => self::REDES_PADRAO,
        'fundamento' => 'Regras provisórias, a confirmar com a norma de frequência do órgão.',
    ];

    protected function casts(): array
    {
        return [
            'turnos_6h' => 'array',
            'turnos_8h' => 'array',
            'tolerancia_minutos' => 'integer',
            'exigir_rede' => 'boolean',
        ];
    }

    /** Regras gravadas do órgão ou, na falta, as provisórias (sem gravar). */
    public static function doOrgao(int $orgaoId): self
    {
        return static::where('orgao_id', $orgaoId)->first()
            ?? new static(['orgao_id' => $orgaoId, ...self::PADRAO]);
    }

    /** @return list<string> */
    public function redes(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) $this->redes_permitidas))));
    }

    /** O registro pelo sistema só vale de dentro da rede do órgão (quando a regra exige). */
    public function redePermite(?string $ip): bool
    {
        return ! $this->exigir_rede || ($ip !== null && IpUtils::checkIp($ip, $this->redes()));
    }

    /** Turnos padrão pela carga da ficha ("8h diárias / 40h semanais" usa os de 8h; o resto, os de 6h). */
    public function turnosPara(Servidor $servidor): array
    {
        return preg_match('/^\s*8\s*h/i', (string) $servidor->carga_horaria) ? $this->turnos_8h : $this->turnos_6h;
    }

    protected function generoAuditoria(): string
    {
        return 'a';
    }

    protected function rotuloAuditoria(): string
    {
        return 'Regras de frequência do órgão';
    }
}
