<?php

namespace App\Console\Commands\Concerns;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * No Docker, `docker compose exec app php artisan ...` roda como root, mas o sistema
 * roda como www-data. Pastas e arquivos que o comando criar em storage ficariam de
 * root, e a tela não conseguiria mais gravar nelas (erro 500 ao enviar planilha).
 * Ao terminar, o comando devolve o que está em storage ao dono da própria pasta.
 */
trait DevolveArquivosAoServidorWeb
{
    protected function devolverArquivosAoServidorWeb(): void
    {
        if (! function_exists('posix_geteuid') || posix_geteuid() !== 0) {
            return;
        }

        $raiz = storage_path();
        $dono = fileowner($raiz);
        $grupo = filegroup($raiz);
        if ($dono === false || $dono === 0) {
            return;
        }

        $itens = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($itens as $item) {
            if (! $item->isLink() && $item->getOwner() !== $dono) {
                @chown($item->getPathname(), $dono);
                @chgrp($item->getPathname(), $grupo);
            }
        }
    }
}
