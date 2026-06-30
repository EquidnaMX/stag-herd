<?php

use Equidna\StagHerd\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('stag-herd.demo.middleware', ['web']))
    ->prefix(config('stag-herd.demo.prefix', 'stag-herd/payments'))
    ->name('stag-herd.payments.')
    ->group(function () {
        if (config('stag-herd.demo.enabled', false)) {
            Route::get('/', [PaymentController::class, 'index'])
                ->name('index');
        }

        Route::post('/', [PaymentController::class, 'store'])
            ->name('store');

        Route::post('/brick/process', [PaymentController::class, 'processBrick'])
            ->name('brick.process');

        Route::post('/paypal/create', [PaymentController::class, 'processPayPalCreate'])
            ->name('paypal.create');

        Route::post('/paypal/capture', [PaymentController::class, 'processPayPalCapture'])
            ->name('paypal.capture');

        Route::post('/stripe/intent', [PaymentController::class, 'processStripeIntent'])
            ->name('stripe.intent');

        Route::post('/stripe/confirm', [PaymentController::class, 'processStripeConfirm'])
            ->name('stripe.confirm');

        Route::post('/provider/lookup', [PaymentController::class, 'providerLookup'])
            ->name('provider.lookup');

        Route::post('/provider/sync', [PaymentController::class, 'providerSync'])
            ->name('provider.sync');

        Route::get('/{payment}', [PaymentController::class, 'show'])
            ->name('show');

        Route::post('/{payment}/sync', [PaymentController::class, 'sync'])
            ->name('sync');

        Route::post('/{payment}/lookup', [PaymentController::class, 'lookup'])
            ->name('lookup');

        Route::post('/{payment}/cancel', [PaymentController::class, 'cancel'])
            ->name('cancel');

        Route::post('/{payment}/refund', [PaymentController::class, 'refund'])
            ->name('refund');
    });
