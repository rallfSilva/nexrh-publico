<?php

use App\Http\Controllers\AfastamentoController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\CargoController;
use App\Http\Controllers\DeclaracaoController;
use App\Http\Controllers\DependenteController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\FeriasController;
use App\Http\Controllers\ImportacaoController;
use App\Http\Controllers\MinhaFichaController;
use App\Http\Controllers\ModeloImportacaoController;
use App\Http\Controllers\OrgaoController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\RegraFeriasController;
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
        // Dossiê e dependentes: dados pessoais, só para RH e administração do órgão.
        Route::middleware(['can:servidores.gerenciar', 'can:servidores.dados_pessoais'])->whereNumber(['servidor', 'dependente', 'documento'])->scopeBindings()->group(function () {
            Route::get('/documentos/a-validar', [DocumentoController::class, 'pendentes'])->name('documentos.pendentes');
            Route::post('/servidores/{servidor}/documentos', [DocumentoController::class, 'store'])->middleware('throttle:30,1')->name('documentos.store');
            Route::post('/servidores/{servidor}/documentos/{documento}/validar', [DocumentoController::class, 'validar'])->name('documentos.validar');
            Route::post('/servidores/{servidor}/documentos/{documento}/recusar', [DocumentoController::class, 'recusar'])->name('documentos.recusar');
            Route::post('/servidores/{servidor}/documentos/{documento}/arquivar', [DocumentoController::class, 'arquivar'])->name('documentos.arquivar');

            Route::get('/servidores/{servidor}/dependentes/novo', [DependenteController::class, 'create'])->name('dependentes.create');
            Route::post('/servidores/{servidor}/dependentes', [DependenteController::class, 'store'])->name('dependentes.store');
            Route::get('/servidores/{servidor}/dependentes/{dependente}/editar', [DependenteController::class, 'edit'])->name('dependentes.edit');
            Route::put('/servidores/{servidor}/dependentes/{dependente}', [DependenteController::class, 'update'])->name('dependentes.update');
            Route::post('/servidores/{servidor}/dependentes/{dependente}/encerrar', [DependenteController::class, 'encerrar'])->name('dependentes.encerrar');
            Route::post('/servidores/{servidor}/dependentes/{dependente}/reativar', [DependenteController::class, 'reativar'])->name('dependentes.reativar');
        });
        Route::get('/servidores/{servidor}/documentos/{documento}/arquivo', [DocumentoController::class, 'arquivo'])
            ->whereNumber(['servidor', 'documento'])->scopeBindings()->middleware(['can:servidores.dados_pessoais', 'throttle:60,1'])->name('documentos.arquivo');

        // Férias: escala do setor para RH, auditoria e chefia; aprovação pela chefia (equipe) ou RH; marcação pelo RH.
        Route::get('/ferias', [FeriasController::class, 'escala'])->middleware('can:ver-escala')->name('ferias.escala');
        Route::middleware('can:aprovar-ferias')->whereNumber(['servidor', 'ferias'])->scopeBindings()->group(function () {
            Route::get('/ferias/a-aprovar', [FeriasController::class, 'pendentes'])->name('ferias.pendentes');
            Route::post('/servidores/{servidor}/ferias/{ferias}/aprovar', [FeriasController::class, 'aprovar'])->name('ferias.aprovar');
            Route::post('/servidores/{servidor}/ferias/{ferias}/recusar', [FeriasController::class, 'recusar'])->name('ferias.recusar');
        });
        Route::middleware('can:ferias.gerenciar')->whereNumber(['servidor', 'ferias', 'afastamento'])->scopeBindings()->group(function () {
            Route::get('/ferias/regras', [RegraFeriasController::class, 'edit'])->name('ferias.regras');
            Route::put('/ferias/regras', [RegraFeriasController::class, 'update'])->name('ferias.regras.update');
            Route::get('/servidores/{servidor}/ferias/marcar', [FeriasController::class, 'create'])->name('ferias.create');
            Route::post('/servidores/{servidor}/ferias', [FeriasController::class, 'store'])->name('ferias.store');
            Route::post('/servidores/{servidor}/ferias/data-base', [FeriasController::class, 'dataBase'])->name('ferias.data-base');
            Route::get('/servidores/{servidor}/ferias/sugestoes', [FeriasController::class, 'sugestoes'])->middleware('throttle:60,1')->name('ferias.sugestoes');
            Route::post('/servidores/{servidor}/ferias/{ferias}/cancelar', [FeriasController::class, 'cancelar'])->name('ferias.cancelar');
            Route::post('/servidores/{servidor}/ferias/{ferias}/interromper', [FeriasController::class, 'interromper'])->name('ferias.interromper');
            Route::get('/servidores/{servidor}/afastamentos/novo', [AfastamentoController::class, 'create'])->name('afastamentos.create');
            Route::post('/servidores/{servidor}/afastamentos', [AfastamentoController::class, 'store'])->name('afastamentos.store');
            Route::post('/servidores/{servidor}/afastamentos/{afastamento}/cancelar', [AfastamentoController::class, 'cancelar'])->name('afastamentos.cancelar');
        });

        Route::get('/servidores/{servidor}/ficha.pdf', [ServidorController::class, 'pdf'])
            ->whereNumber('servidor')->middleware(['can:servidores.dados_pessoais', 'throttle:30,1'])->name('servidores.pdf');

        Route::get('/minha-ficha', [MinhaFichaController::class, 'show'])->name('minha-ficha');
        Route::get('/minha-ficha/ficha.pdf', [MinhaFichaController::class, 'pdf'])->middleware('throttle:10,1')->name('minha-ficha.pdf');
        Route::post('/minha-ficha/documentos', [MinhaFichaController::class, 'enviarDocumento'])->middleware('throttle:20,1')->name('minha-ficha.documentos.store');
        Route::get('/minha-ficha/documentos/{documento}/arquivo', [MinhaFichaController::class, 'arquivo'])
            ->whereNumber('documento')->middleware('throttle:60,1')->name('minha-ficha.documentos.arquivo');

        Route::get('/minha-ficha/ferias/pedir', [MinhaFichaController::class, 'pedirFerias'])->name('minha-ficha.ferias.create');
        Route::post('/minha-ficha/ferias', [MinhaFichaController::class, 'enviarPedidoFerias'])->middleware('throttle:20,1')->name('minha-ficha.ferias.store');
        Route::get('/minha-ficha/ferias/sugestoes', [MinhaFichaController::class, 'sugestoesFerias'])->middleware('throttle:60,1')->name('minha-ficha.ferias.sugestoes');
        Route::post('/minha-ficha/ferias/{ferias}/desistir', [MinhaFichaController::class, 'desistirFerias'])->whereNumber('ferias')->name('minha-ficha.ferias.desistir');

        Route::get('/auditoria', [AuditoriaController::class, 'index'])->middleware('can:auditoria.ver')->name('auditoria.index');

        Route::get('/modelos-importacao', [ModeloImportacaoController::class, 'index'])->name('modelos.index');
        Route::get('/modelos-importacao/{arquivo}', [ModeloImportacaoController::class, 'baixar'])
            ->where('arquivo', '[A-Za-z0-9_\-]+\.xlsx')
            ->name('modelos.baixar');
    });
});
