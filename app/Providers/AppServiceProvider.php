<?php

namespace App\Providers;

use App\Listeners\AuditarAutenticacao;
use App\Models\Servidor;
use App\Models\User;
use App\Relatorios\Catalogo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        // Escala de férias: o órgão todo para RH, administração e auditoria; a equipe para a chefia.
        Gate::define('ver-escala', fn (User $u) => $u->can('ferias.ver') || $u->can('ferias.aprovar'));
        // Frequência: o órgão todo para RH, administração e auditoria; a equipe para a chefia.
        Gate::define('ver-frequencia', fn (User $u) => $u->can('frequencia.ver') || $u->can('frequencia.equipe'));
        Gate::define('aprovar-ferias', fn (User $u) => $u->can('ferias.gerenciar') || $u->can('ferias.aprovar'));
        Gate::define('analisar-justificativas', fn (User $u) => $u->can('frequencia.gerenciar') || $u->can('frequencia.equipe'));

        // Relatórios: cada um diz quem pode ver; o menu aparece para quem pode ver algum.
        Gate::define('ver-relatorios', fn (User $u) => Catalogo::para($u) !== []);

        Gate::define('importar-planilhas', fn (User $u) => $u->can('cargos.gerenciar') || $u->can('servidores.gerenciar') || $u->can('ferias.gerenciar') || $u->can('frequencia.gerenciar'));

        // API: 60 consultas por minuto por chave (ou por IP, para quem chega sem chave).
        RateLimiter::for('api-chave', fn (Request $request) => Limit::perMinute(60)->by($request->bearerToken() ? hash('sha256', $request->bearerToken()) : $request->ip())
            ->response(fn () => response()->json(['erro' => 'Muitas consultas seguidas. Espere um minuto.'], 429)));

        // Usuários de outro órgão respondem 404, como se não existissem.
        // Servidor fora do órgão ou fora da equipe da chefia responde 404.
        Route::bind('servidor', fn (string $id) => Servidor::visiveisPara(request()->user() ?? abort(401))->findOrFail($id));

        Route::bind('usuario', fn (string $id) => User::visiveisPara(request()->user() ?? abort(401))->findOrFail($id));
    }
}
