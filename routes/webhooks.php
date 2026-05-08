<?php

use Equidna\StagHerd\Http\Controllers\PayPalController;
use Equidna\StagHerd\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::group(
    [
        'prefix' => config('stag-herd.route_prefix', 'stag-herd'),
        'middleware' => ['api', 'throttle:webhook'],
    ],
    function () {
        // Unified Webhook Controller for refactored providers
        Route::match(['get', 'post'], '/mercadopago', [WebhookController::class, 'handle'])
            ->defaults('provider', 'mercadopago')
            ->name('stag-herd.mercadopago');

        // PayPal Routes
        Route::match(['get', 'post'], '/paypal', [WebhookController::class, 'handle'])
            ->defaults('provider', 'paypal')
            ->name('stag-herd.paypal');

        Route::get('/paypal/return', [PayPalController::class, 'return'])
            ->name('stag-herd.paypal.return');

        Route::get('/paypal/cancel', [PayPalController::class, 'cancel'])
            ->name('stag-herd.paypal.cancel');

        Route::match(['get', 'post'], '/googlepay', [WebhookController::class, 'handle'])
            ->defaults('provider', 'googlepay')
            ->name('stag-herd.googlepay');

        Route::match(['get', 'post'], '/clip', [WebhookController::class, 'handle'])
            ->defaults('provider', 'clip')
            ->name('stag-herd.clip');

        // Legacy routes now also delegate to unified controller
        Route::match(['get', 'post'], '/openpay', [WebhookController::class, 'handle'])
            ->defaults('provider', 'openpay')
            ->name('stag-herd.openpay');

        Route::match(['get', 'post'], '/conekta', [WebhookController::class, 'handle'])
            ->defaults('provider', 'conekta')
            ->name('stag-herd.conekta');
    },
);
