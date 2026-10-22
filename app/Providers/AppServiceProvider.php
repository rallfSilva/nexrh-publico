<?php

namespace App\Providers;

use App\Listeners\AuditarAutenticacao;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::subscribe(AuditarAutenticacao::class);

        Password::defaults(fn () => Password::min(10)->letters()->mixedCase()->numbers());

        // Só o administrador geral mexe em outro administrador geral.
        Gate::define('alterar-usuario', fn (User $quem, User $alvo) => $quem->can('usuarios.gerenciar')
            && ($quem->ehAdministradorGeral() || ! $alvo->hasRole('administrador-geral')));

        // RH, administração e auditoria veem o órgão todo; a chefia, só a própria equipe.
        Gate::define('listar-servidores', fn (User $u) => $u->can('servidores.ver') || $u->can('servidores.ver_equipe'));

        // Dossiê e dependentes são dados pessoais: quem gerencia servidores e vê dados pessoais.
        Gate::define('gerenciar-dossie', fn (User $u) => $u->can('servidores.gerenciar') && $u->can('servidores.dados_pessoais'));

        Gate::define('importar-planilhas', fn (User $u) => $u->can('cargos.gerenciar') || $u->can('servidores.gerenciar'));

        // Usuários de outro órgão respondem 404, como se não existissem.
        // Servidor fora do órgão ou fora da equipe da chefia responde 404.
        Route::bind('servidor', fn (string $id) => Servidor::visiveisPara(request()->user() ?? abort(401))->findOrFail($id));

        Route::bind('usuario', fn (string $id) => User::visiveisPara(request()->user() ?? abort(401))->findOrFail($id));
    }
}
