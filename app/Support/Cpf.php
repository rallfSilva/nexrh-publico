<?php

namespace App\Support;

class Cpf
{
    public static function somenteDigitos(?string $cpf): string
    {
        return preg_replace('/\D/', '', (string) $cpf);
    }

    /** Confere o formato e os dígitos verificadores. */
    public static function valido(?string $cpf): bool
    {
        $d = self::somenteDigitos($cpf);

        if (strlen($d) !== 11 || preg_match('/^(\d)\1{10}$/', $d)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $d[$i] * (($t + 1) - $i);
            }
            if ((int) $d[$t] !== ((10 * $soma) % 11) % 10) {
                return false;
            }
        }

        return true;
    }

    /**
     * Impressão digital do CPF (HMAC com a chave da aplicação): permite buscar e
     * impedir cadastro em dobro sem guardar o CPF aberto.
     */
    public static function hash(?string $cpf): string
    {
        $chave = (string) config('app.key');

        if (str_starts_with($chave, 'base64:')) {
            $chave = base64_decode(substr($chave, 7));
        }

        return hash_hmac('sha256', self::somenteDigitos($cpf), $chave);
    }

    /** Formato da ficha: 123.456.789-09 */
    public static function formatar(?string $cpf): ?string
    {
        $d = self::somenteDigitos($cpf);

        return strlen($d) === 11 ? substr($d, 0, 3).'.'.substr($d, 3, 3).'.'.substr($d, 6, 3).'-'.substr($d, 9, 2) : ($cpf ?: null);
    }

    /** Exibição segura: ***.456.789-** */
    public static function mascarar(?string $cpf): ?string
    {
        if ($cpf === null || trim($cpf) === '') {
            return null;
        }

        $d = self::somenteDigitos($cpf);

        return strlen($d) === 11 ? '***.'.substr($d, 3, 3).'.'.substr($d, 6, 3).'-**' : '***';
    }
}
