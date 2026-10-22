<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\CargoController;
use App\Http\Controllers\DeclaracaoController;
use App\Http\Controllers\ImportacaoController;
use App\Http\Controllers\MinhaFichaController;
use App\Http\Controllers\ModeloImportacaoController;
use App\Http\Controllers\OrgaoController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\SegurancaController;
use App\Http\Controllers\ServidorController;
use App\Http\Controllers\UnidadeController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VagaController;
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

        Route::middleware('can:cargos.gerenciar')->group(function () {
            Route::resource('cargos', CargoController::class)->only(['create', 'store', 'edit', 'update'])->parameters(['cargos' => 'cargo']);
            Route::get('/vagas/{vaga}/edit', [VagaController::class, 'edit'])->name('vagas.edit');
            Route::put('/vagas/{vaga}', [VagaController::class, 'update'])->name('vagas.update');
        });
        Route::middleware('can:importar-planilhas')->group(function () {
            Route::get('/importacoes', [ImportacaoController::class, 'index'])->name('importacoes.index');
            Route::get('/importacoes/nova', [ImportacaoController::class, 'create'])->name('importacoes.create');
            Route::post('/importacoes', [ImportacaoController::class, 'store'])->middleware('throttle:10,1')->name('importacoes.store');
            Route::get('/importacoes/{importacao}', [ImportacaoController::class, 'show'])->whereNumber('importacao')->name('importacoes.show');
            Route::post('/importacoes/{importacao}/aplicar', [ImportacaoController::class, 'aplicar'])->whereNumber('importacao')->name('importacoes.aplicar');
            Route::post('/importacoes/{importacao}/cancelar', [ImportacaoController::class, 'cancelar'])->whereNumber('importacao')->name('importacoes.cancelar');
        });
        Route::middleware('can:cargos.ver')->group(function () {
            Route::get('/cargos', [CargoController::class, 'index'])->name('cargos.index');
            Route::get('/cargos/{cargo}', [CargoController::class, 'show'])->whereNumber('cargo')->name('cargos.show');
        });

        Route::middleware('can:servidores.gerenciar')->group(function () {
            Route::get('/servidores/novo', [ServidorController::class, 'create'])->name('servidores.create');
            Route::post('/servidores', [ServidorController::class, 'store'])->name('servidores.store');
            Route::get('/servidores/{servidor}/editar', [ServidorController::class, 'edit'])->whereNumber('servidor')->name('servidores.edit');
            Route::put('/servidores/{servidor}', [ServidorController::class, 'update'])->whereNumber('servidor')->name('servidores.update');
            Route::post('/servidores/{servidor}/desligar', [ServidorController::class, 'desligar'])->whereNumber('servidor')->name('servidores.desligar');
            Route::get('/servidores/{servidor}/declaracoes/{tipo}/nova', [DeclaracaoController::class, 'create'])
                ->whereNumber('servidor')->whereIn('tipo', ['parentesco', 'acumulacao', 'bens'])->name('declaracoes.create');
            Route::post('/servidores/{servidor}/declaracoes/{tipo}', [DeclaracaoController::class, 'store'])
                ->whereNumber('servidor')->whereIn('tipo', ['parentesco', 'acumulacao', 'bens'])->name('declaracoes.store');
        });
        Route::middleware('can:listar-servidores')->group(function () {
            Route::get('/servidores', [ServidorController::class, 'index'])->name('servidores.index');
            Route::get('/servidores/{servidor}', [ServidorController::class, 'show'])->whereNumber('servidor')->name('servidores.show');
        });
        Route::get('/servidores/{servidor}/ficha.pdf', [ServidorController::class, 'pdf'])
            ->whereNumber('servidor')->middleware(['can:servidores.dados_pessoais', 'throttle:30,1'])->name('servidores.pdf');

        Route::get('/minha-ficha', [MinhaFichaController::class, 'show'])->name('minha-ficha');
        Route::get('/minha-ficha/ficha.pdf', [MinhaFichaController::class, 'pdf'])->middleware('throttle:10,1')->name('minha-ficha.pdf');

        Route::get('/auditoria', [AuditoriaController::class, 'index'])->middleware('can:auditoria.ver')->name('auditoria.index');

        Route::get('/modelos-importacao', [ModeloImportacaoController::class, 'index'])->name('modelos.index');
        Route::get('/modelos-importacao/{arquivo}', [ModeloImportacaoController::class, 'baixar'])
            ->where('arquivo', '[A-Za-z0-9_\-]+\.xlsx')
            ->name('modelos.baixar');
    });
});
