<?php

use Equidna\StagHerd\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('stag-herd.webhooks.routes.middleware', ['api']))
    ->prefix(config('stag-herd.webhooks.routes.prefix', 'stag-herd/webhooks'))
    ->name('stag-herd.webhooks.')
    ->group(function () {
        Route::post('/{provider}/{credentialContext}', [WebhookController::class, 'handle'])
            ->where('provider', '[A-Za-z0-9_-]+')
            ->where('credentialContext', '[A-Za-z0-9_-]+')
            ->name('contextual');

        Route::match(['get', 'post'], '/mercado-pago', [WebhookController::class, 'handle'])
            ->defaults('provider', 'mercado_pago')
            ->name('mercado-pago');

        Route::match(['get', 'post'], '/paypal', [WebhookController::class, 'handle'])->defaults('provider', 'paypal')->name('paypal');

        Route::match(['get', 'post'], '/stripe', [WebhookController::class, 'handle'])->defaults('provider', 'stripe')->name('stripe');
    });
