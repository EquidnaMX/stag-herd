<?php

use Illuminate\Support\Facades\Route;
use Equidna\StagHerd\Http\Controllers\WebhookController;

Route::group(
    [
        'prefix' => config('stag-herd.route_prefix', 'stag-herd'), 
        'middleware' => 'api'
    ], function () {
        // Unified Webhook Controller for refactored providers
        Route::match(['get', 'post'], '/mercadopago', [WebhookController::class, 'handle'])->defaults('provider', 'mercadopago')->name('stag-herd.mercadopago');
        Route::match(['get', 'post'], '/paypal', [WebhookController::class, 'handle'])->defaults('provider', 'paypal')->name('stag-herd.paypal');
        Route::match(['get', 'post'], '/googlepay', [WebhookController::class, 'handle'])->defaults('provider', 'googlepay')->name('stag-herd.googlepay');

        // Legacy Controllers (TODO: Refactor Handlers)
        Route::match(['get', 'post'], '/openpay', [\Equidna\StagHerd\Http\Controllers\OpenpayController::class, 'handle'])->name('stag-herd.openpay');
        Route::match(['get', 'post'], '/conekta', [\Equidna\StagHerd\Http\Controllers\ConektaController::class, 'handle'])->name('stag-herd.conekta');
        Route::match(['get', 'post'], '/kueskipay', [\Equidna\StagHerd\Http\Controllers\KueskiController::class, 'handle'])->name('stag-herd.kueskipay');
    }
);
