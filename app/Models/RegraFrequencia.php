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
 * 7h30 às 13h30, ou 8h das 8h às 12h e das 14h às 18h), 15 minutos de tolerância,
 * registro só de dentro da rede do órgão e dispensa do ponto para secretário,
 * adjunto e coordenadores. O mês fecha até o dia 15 do mês seguinte.
 */
#[Fillable(['orgao_id', 'turnos_6h', 'turnos_8h', 'tolerancia_minutos', 'exigir_rede', 'redes_permitidas', 'cargos_dispensados', 'dia_fechamento', 'fundamento'])]
class RegraFrequencia extends Model
{
    use Auditable, BelongsToOrgao;

    protected $table = 'regras_frequencia';

    /** Redes privadas: em instalação local ou na rede interna, só quem está dentro do órgão chega a elas. */
    public const REDES_PADRAO = '10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 127.0.0.0/8, ::1';

    public const PADRAO = [
        'turnos_6h' => [['07:30', '13:30']],
        'turnos_8h' => [['08:00', '12:00'], ['14:00', '18:00']],
        'tolerancia_minutos' => 15,
        'exigir_rede' => true,
        'redes_permitidas' => self::REDES_PADRAO,
        'cargos_dispensados' => null,
        'dia_fechamento' => 15,
        'fundamento' => 'Regras provisórias, a confirmar com a norma de frequência do órgão.',
    ];

    /** Sem escolha gravada, ficam dispensados os cargos com estes começos de nome. */
    public const CARGOS_DISPENSADOS_PADRAO = ['Secretári', 'Coordenador'];

    /** @var array<int, string>|null cargo_id => denominação, calculado uma vez */
    private ?array $dispensados = null;

    protected function casts(): array
    {
        return [
            'turnos_6h' => 'array',
            'turnos_8h' => 'array',
            'tolerancia_minutos' => 'integer',
            'exigir_rede' => 'boolean',
            'cargos_dispensados' => 'array',
            'dia_fechamento' => 'integer',
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

    /** @return array<int, string> cargo_id => denominação dos cargos dispensados do ponto */
    public function cargosDispensados(): array
    {
        if ($this->dispensados !== null) {
            return $this->dispensados;
        }

        $query = Cargo::where('orgao_id', $this->orgao_id);
        if ($this->cargos_dispensados !== null) {
            $query->whereIn('id', $this->cargos_dispensados ?: [0]);
        } else {
            $query->where(fn ($q) => collect(self::CARGOS_DISPENSADOS_PADRAO)->each(fn ($inicio) => $q->orWhere('denominacao', 'ilike', $inicio.'%')));
        }

        return $this->dispensados = $query->orderBy('denominacao')->pluck('denominacao', 'id')->all();
    }

    /** Cargo que dispensa o servidor do ponto, ou null. */
    public function dispensaPeloCargo(Servidor $servidor): ?string
    {
        $cargoId = $servidor->vaga?->cargo_id;

        return $cargoId ? ($this->cargosDispensados()[$cargoId] ?? null) : null;
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
