<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\ModeloImportacaoController;
use App\Http\Controllers\OrgaoController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\SegurancaController;
use App\Http\Controllers\UnidadeController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/painel');

Route::middleware(['auth', 'ativo'])->group(function () {
    // Fica fora da exigência de 2FA: é aqui que o administrador ativa a verificação.
    Route::get('/seguranca', SegurancaController::class)->name('seguranca');

    Route::middleware('dois-fatores')->group(function () {
        Route::get('/painel', PainelController::class)->middleware('can:painel.ver')->name('painel');

        Route::resource('orgaos', OrgaoController::class)
            ->except(['show', 'destroy'])
            ->parameters(['orgaos' => 'orgao'])
            ->middleware('can:orgaos.gerenciar');

        Route::get('/unidades', [UnidadeController::class, 'index'])->middleware('can:unidades.ver')->name('unidades.index');
        Route::resource('unidades', UnidadeController::class)
            ->except(['index', 'show'])
            ->parameters(['unidades' => 'unidade'])
            ->middleware('can:unidades.gerenciar');

        Route::get('/usuarios', [UsuarioController::class, 'index'])->middleware('can:usuarios.ver')->name('usuarios.index');
        Route::middleware('can:usuarios.gerenciar')->group(function () {
            Route::resource('usuarios', UsuarioController::class)
                ->except(['index', 'show', 'destroy'])
                ->parameters(['usuarios' => 'usuario']);
            Route::patch('/usuarios/{usuario}/ativo', [UsuarioController::class, 'alternarAtivo'])->name('usuarios.ativo');
            Route::post('/usuarios/{usuario}/link-senha', [UsuarioController::class, 'enviarLinkSenha'])
                ->middleware('throttle:6,1')
                ->name('usuarios.link-senha');
        });

        Route::get('/auditoria', [AuditoriaController::class, 'index'])->middleware('can:auditoria.ver')->name('auditoria.index');

        Route::get('/modelos-importacao', [ModeloImportacaoController::class, 'index'])->name('modelos.index');
        Route::get('/modelos-importacao/{arquivo}', [ModeloImportacaoController::class, 'baixar'])
            ->where('arquivo', '[A-Za-z0-9_\-]+\.xlsx')
            ->name('modelos.baixar');
    });
});
