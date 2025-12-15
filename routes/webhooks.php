<?php

use Illuminate\Support\Facades\Route;
use Equidna\StagHerd\Http\Controllers\WebhookController;

Route::group(
    [
        'prefix' => config('stag-herd.route_prefix', 'stag-herd'),
        'middleware' => ['api', 'throttle:webhook']
    ],
    function () {
        // Unified Webhook Controller for refactored providers
        Route::match(['get', 'post'], '/mercadopago', [WebhookController::class, 'handle'])
            ->defaults('provider', 'mercadopago')
            ->name('stag-herd.mercadopago');

        Route::match(['get', 'post'], '/paypal', [WebhookController::class, 'handle'])
            ->defaults('provider', 'paypal')
            ->name('stag-herd.paypal');

        Route::match(['get', 'post'], '/googlepay', [WebhookController::class, 'handle'])
            ->defaults('provider', 'googlepay')
            ->name('stag-herd.googlepay');

        // Legacy routes now also delegate to unified controller
        Route::match(['get', 'post'], '/openpay', [WebhookController::class, 'handle'])
            ->defaults('provider', 'openpay')
            ->name('stag-herd.openpay');

        Route::match(['get', 'post'], '/conekta', [WebhookController::class, 'handle'])
            ->defaults('provider', 'conekta')
            ->name('stag-herd.conekta');

        Route::match(['get', 'post'], '/kueskipay', [WebhookController::class, 'handle'])
            ->defaults('provider', 'kueski')
            ->name('stag-herd.kueskipay');
    }
);
