<?php

use App\Http\Controllers\Api\ConsultaController;
use Illuminate\Support\Facades\Route;

// API de consulta, só leitura. Autenticação pela chave criada em "Chaves de API" (cabeçalho Authorization: Bearer nxr_...).
Route::prefix('v1')->middleware(['api.chave', 'throttle:api-chave'])->group(function () {
    Route::get('/', [ConsultaController::class, 'inicio']);
    Route::get('/unidades', [ConsultaController::class, 'unidades'])->middleware('api.chave:unidades');
    Route::get('/cargos', [ConsultaController::class, 'cargos'])->middleware('api.chave:cargos');
    Route::get('/servidores', [ConsultaController::class, 'servidores'])->middleware('api.chave:servidores');
    Route::get('/servidores/{id}', [ConsultaController::class, 'servidor'])->whereNumber('id')->middleware('api.chave:servidores');
    Route::get('/ferias', [ConsultaController::class, 'ferias'])->middleware('api.chave:ferias');
    Route::get('/ausencias', [ConsultaController::class, 'ausencias'])->middleware('api.chave:ferias');
    Route::get('/frequencia', [ConsultaController::class, 'frequencia'])->middleware('api.chave:frequencia');
});
