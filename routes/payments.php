<?php

use Equidna\StagHerd\Http\Controllers\PaymentDemoController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('stag-herd.ui.middleware', ['web']))
    ->prefix(config('stag-herd.ui.prefix', 'stag-herd/payments'))
    ->name('stag-herd.payments.')
    ->group(function () {
        Route::get('/', [PaymentDemoController::class, 'index'])->name('index');

        Route::post('/', [PaymentDemoController::class, 'store'])->name('store');

        Route::post('/brick/process', [PaymentDemoController::class, 'processBrick'])
            ->name('brick.process');

        Route::post('/provider/lookup', [PaymentDemoController::class, 'providerLookup'])
            ->name('provider.lookup');

        Route::post('/provider/sync', [PaymentDemoController::class, 'providerSync'])
            ->name('provider.sync');

        Route::get('/{payment}', [PaymentDemoController::class, 'show'])
            ->name('show');

        Route::post('/{payment}/sync', [PaymentDemoController::class, 'sync'])
            ->name('sync');

        Route::post('/{payment}/cancel', [PaymentDemoController::class, 'cancel'])
            ->name('cancel');

        Route::post('/{payment}/refund', [PaymentDemoController::class, 'refund'])
            ->name('refund');
    });
