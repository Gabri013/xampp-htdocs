<?php

use App\Http\Controllers\ContaPagarController;
use App\Http\Controllers\FinanceiroController;
use App\Http\Controllers\NotificacaoController;
use App\Http\Controllers\OSController;
use App\Http\Controllers\ProducaoController;
use App\Http\Controllers\VendaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['ponte'])->group(function () {

    Route::get('/notificacoes', [NotificacaoController::class, 'index'])
        ->name('notificacoes.index');

    Route::post('/notificacoes/{id}/marcar-lida', [NotificacaoController::class, 'marcarLida'])
        ->name('notificacoes.marcar-lida');

    Route::get('/financeiro', [FinanceiroController::class, 'index'])
        ->name('financeiro.index');

    Route::post('/financeiro/{id}/marcar-pago', [FinanceiroController::class, 'marcarPago'])
        ->name('financeiro.marcar-pago');

    Route::get('/contas-pagar', [ContaPagarController::class, 'index'])
        ->name('contas-pagar.index');

    Route::post('/contas-pagar/{id}/marcar-pago', [ContaPagarController::class, 'marcarPago'])
        ->name('contas-pagar.marcar-pago');

    Route::get('/producao', [ProducaoController::class, 'index'])
        ->name('producao.index');

    Route::post('/producao/{osId}/iniciar-etapa', [ProducaoController::class, 'iniciarEtapa'])
        ->name('producao.iniciar-etapa');

    Route::post('/producao/{osId}/finalizar-etapa', [ProducaoController::class, 'finalizarEtapa'])
        ->name('producao.finalizar-etapa');

    Route::get('/vendas', [VendaController::class, 'index'])
        ->name('vendas.index');

    Route::get('/os', [OSController::class, 'index'])
        ->name('os.index');

    Route::get('/os/{ordemServico}', [OSController::class, 'show'])
        ->name('os.show');

});