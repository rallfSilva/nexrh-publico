<?php

namespace App\Importacao;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/** Normalização dos valores lidos das planilhas. */
class Texto
{
    private const MINUSCULAS = ['de', 'da', 'do', 'das', 'dos', 'e', 'para', 'p/', 'a', 'o', 'em'];

    /** "SECRETÁRIO DE ESTADO DE LICITAÇÃO" → "Secretário de Estado de Licitação" (siglas com algarismos romanos ficam em maiúsculas). */
    public static function titulo(string $texto): string
    {
        $palavras = explode(' ', mb_strtolower(trim($texto)));

        foreach ($palavras as $i => $p) {
            if (preg_match('/^(i|ii|iii|iv|v|vi|vii|viii|ix|x)$/', $p)) {
                $palavras[$i] = mb_strtoupper($p);
            } elseif ($i === 0 || ! in_array($p, self::MINUSCULAS, true)) {
                $palavras[$i] = mb_strtoupper(mb_substr($p, 0, 1)).mb_substr($p, 1);
            }
        }

        return implode(' ', $palavras);
    }

    /** Chave para comparar textos ignorando maiúsculas, acentos e espaços. */
    public static function chave(?string $texto): string
    {
        $t = mb_strtolower(trim((string) $texto));
        $t = strtr($t, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c']);

        return preg_replace('/[^a-z0-9]+/', '', $t);
    }

    /** Valor de célula como texto, tratando traços e vazios como nulo. */
    public static function valor(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }

        if ($v instanceof DateTimeInterface) {
            return $v->format('d/m/Y');
        }

        if (is_float($v) && floor($v) === $v) {
            $v = (int) $v;
        }

        $t = trim((string) $v);

        return in_array($t, ['', '-', '−', '–', '—'], true) ? null : $t;
    }

    public static function inteiro(mixed $v): ?int
    {
        if (is_int($v)) {
            return $v;
        }
        if (is_float($v) && floor($v) === $v) {
            return (int) $v;
        }
        $t = self::valor($v);

        return $t !== null && preg_match('/^\d+$/', $t) ? (int) $t : null;
    }

    public static function decimal(mixed $v): ?float
    {
        if (is_int($v) || is_float($v)) {
            return round((float) $v, 2);
        }
        $t = self::valor($v);
        if ($t === null) {
            return null;
        }
        $t = str_replace(['R$', ' '], '', $t);
        if (str_contains($t, ',')) {
            $t = str_replace(['.', ','], ['', '.'], $t);
        }

        return is_numeric($t) ? round((float) $t, 2) : null;
    }

    /** Data em Y-m-d, aceitando data do Excel ou texto DD/MM/AAAA. */
    public static function data(mixed $v): ?string
    {
        if ($v instanceof DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        $t = self::valor($v);
        if ($t === null || ! preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $t, $m)) {
            return null;
        }
        if (! checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return null;
        }

        return CarbonImmutable::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->format('Y-m-d');
    }
}
