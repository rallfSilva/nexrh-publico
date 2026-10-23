<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrgao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Feriado ou ponto facultativo do órgão: o dia não conta como dia de trabalho no espelho. */
#[Fillable(['orgao_id', 'data', 'nome', 'tipo'])]
class Feriado extends Model
{
    use Auditable, BelongsToOrgao;

    protected $table = 'feriados';

    public const TIPOS = ['feriado' => 'Feriado', 'ponto_facultativo' => 'Ponto facultativo'];

    /**
     * Feriados nacionais e de Roraima (Lei Federal 662/1949 e Lei 14.759/2023;
     * Dia de Roraima e aniversário de Boa Vista). Carnaval, Paixão e Corpus Christi
     * mudam de data todo ano. O RH confere e ajusta em Frequência › Regras.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function sugeridos(int $ano): array
    {
        $pascoa = self::pascoa($ano);
        $d = fn (string $m) => $pascoa->modify($m)->format('Y-m-d');

        $lista = [
            "{$ano}-01-01" => ['Confraternização Universal', 'feriado'],
            $d('-48 days') => ['Carnaval', 'ponto_facultativo'],
            $d('-47 days') => ['Carnaval', 'ponto_facultativo'],
            $d('-2 days') => ['Paixão de Cristo', 'feriado'],
            "{$ano}-04-21" => ['Tiradentes', 'feriado'],
            "{$ano}-05-01" => ['Dia do Trabalho', 'feriado'],
            $d('+60 days') => ['Corpus Christi', 'ponto_facultativo'],
            "{$ano}-07-09" => ['Aniversário de Boa Vista', 'feriado'],
            "{$ano}-09-07" => ['Independência do Brasil', 'feriado'],
            "{$ano}-10-05" => ['Dia de Roraima', 'feriado'],
            "{$ano}-10-12" => ['Nossa Senhora Aparecida', 'feriado'],
            "{$ano}-10-28" => ['Dia do Servidor Público', 'ponto_facultativo'],
            "{$ano}-11-02" => ['Finados', 'feriado'],
            "{$ano}-11-15" => ['Proclamação da República', 'feriado'],
            "{$ano}-11-20" => ['Dia Nacional de Zumbi e da Consciência Negra', 'feriado'],
            "{$ano}-12-25" => ['Natal', 'feriado'],
        ];
        ksort($lista);

        return $lista;
    }

    /** Domingo de Páscoa (algoritmo de Meeus/Jones/Butcher), sem depender da extensão calendar do PHP. */
    public static function pascoa(int $ano): \DateTimeImmutable
    {
        $a = $ano % 19;
        $b = intdiv($ano, 100);
        $c = $ano % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $ano, $mes, $dia));
    }

    protected function casts(): array
    {
        return ['data' => 'date'];
    }

    protected function rotuloAuditoria(): string
    {
        return ($this->tipo === 'ponto_facultativo' ? 'Ponto facultativo' : 'Feriado').' de '.$this->data?->format('d/m/Y');
    }
}
