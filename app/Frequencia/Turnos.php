<?php

namespace App\Frequencia;

/**
 * Horário de trabalho como lista de turnos [entrada, saída], ex.:
 * [['07:30', '13:30']] (6h corridas) ou [['08:00', '12:00'], ['14:00', '18:00']].
 * Na tela, o RH escreve "07:30-13:30" ou "08:00-12:00, 14:00-18:00".
 */
class Turnos
{
    /** @return list<array{0: string, 1: string}>|null null quando o texto não é um horário válido */
    public static function ler(?string $texto): ?array
    {
        $partes = array_values(array_filter(array_map('trim', preg_split('/[,;]|\s+e\s+/u', (string) $texto))));

        if ($partes === [] || count($partes) > 3) {
            return null;
        }

        $turnos = [];
        $anterior = -1;
        foreach ($partes as $parte) {
            if (! preg_match('/^(\d{1,2})[:h](\d{2})\s*(?:-|a|às|as|até)\s*(\d{1,2})[:h](\d{2})$/u', $parte, $m)) {
                return null;
            }
            [$h1, $m1, $h2, $m2] = array_map('intval', array_slice($m, 1));
            if ($h1 > 23 || $h2 > 23 || $m1 > 59 || $m2 > 59) {
                return null;
            }
            $de = $h1 * 60 + $m1;
            $ate = $h2 * 60 + $m2;
            if ($ate <= $de || $de <= $anterior) {
                return null;
            }
            $anterior = $ate;
            $turnos[] = [sprintf('%02d:%02d', $h1, $m1), sprintf('%02d:%02d', $h2, $m2)];
        }

        return $turnos;
    }

    /** "07:30-13:30" ou "08:00-12:00, 14:00-18:00", para o campo do formulário. */
    public static function campo(?array $turnos): string
    {
        return implode(', ', array_map(fn ($t) => $t[0].'-'.$t[1], $turnos ?? []));
    }

    /** "07:30 às 13:30" ou "08:00 às 12:00 e 14:00 às 18:00", para leitura. */
    public static function texto(?array $turnos): string
    {
        return implode(' e ', array_map(fn ($t) => $t[0].' às '.$t[1], $turnos ?? []));
    }

    public static function minutos(?array $turnos): int
    {
        return array_sum(array_map(fn ($t) => self::emMinutos($t[1]) - self::emMinutos($t[0]), $turnos ?? []));
    }

    public static function emMinutos(string $hora): int
    {
        [$h, $m] = array_map('intval', explode(':', $hora));

        return $h * 60 + $m;
    }

    /** 390 → "6h30", -45 → "-0h45". */
    public static function horas(int $minutos): string
    {
        $sinal = $minutos < 0 ? '-' : '';
        $minutos = abs($minutos);

        return $sinal.intdiv($minutos, 60).'h'.str_pad((string) ($minutos % 60), 2, '0', STR_PAD_LEFT);
    }
}
