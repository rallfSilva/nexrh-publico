<?php

namespace App\Relatorios;

use App\Models\User;

/** Os relatórios do sistema, na ordem em que aparecem. */
class Catalogo
{
    public const RELATORIOS = [
        Servidores::class,
        QuadroDeCargos::class,
        Ferias::class,
        SaldoDeFerias::class,
        Afastamentos::class,
        BoletimDeFrequencia::class,
        Pendencias::class,
        Dependentes::class,
    ];

    /** @return list<Relatorio> */
    public static function para(User $user): array
    {
        return array_values(array_filter(array_map(fn ($c) => app($c), self::RELATORIOS), fn (Relatorio $r) => $r->permitido($user)));
    }

    public static function encontrar(string $chave): ?Relatorio
    {
        foreach (self::RELATORIOS as $classe) {
            $r = app($classe);
            if ($r->chave() === $chave) {
                return $r;
            }
        }

        return null;
    }
}
