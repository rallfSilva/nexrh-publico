<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Perfis e permissões do sistema. Pode rodar quantas vezes quiser (é idempotente)
 * e roda a cada instalação ou atualização.
 */
class PerfisSeeder extends Seeder
{
    /** @var array<string, string> */
    public const PERMISSOES = [
        'orgaos.gerenciar' => 'Cadastrar e alterar órgãos',
        'unidades.ver' => 'Ver o organograma e as unidades',
        'unidades.gerenciar' => 'Cadastrar e alterar unidades',
        'usuarios.ver' => 'Ver os usuários do órgão',
        'usuarios.gerenciar' => 'Cadastrar usuários e definir perfis',
        'auditoria.ver' => 'Consultar a trilha de auditoria',
        'painel.ver' => 'Ver o painel',
    ];

    /** @var array<string, list<string>> */
    public const PERFIS = [
        'administrador-geral' => ['*'],
        'administrador-orgao' => ['unidades.ver', 'unidades.gerenciar', 'usuarios.ver', 'usuarios.gerenciar', 'auditoria.ver', 'painel.ver'],
        'rh' => ['unidades.ver', 'usuarios.ver', 'painel.ver'],
        'chefia' => ['unidades.ver', 'painel.ver'],
        'servidor' => ['painel.ver'],
        'auditor' => ['unidades.ver', 'usuarios.ver', 'auditoria.ver', 'painel.ver'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(self::PERMISSOES) as $nome) {
            Permission::findOrCreate($nome, 'web');
        }

        foreach (self::PERFIS as $perfil => $permissoes) {
            $role = Role::findOrCreate($perfil, 'web');
            $role->syncPermissions($permissoes === ['*'] ? array_keys(self::PERMISSOES) : $permissoes);
        }

        assert(array_keys(self::PERFIS) === array_keys(User::PERFIS));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
