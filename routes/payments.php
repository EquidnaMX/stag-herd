<?php

use Equidna\StagHerd\Http\Controllers\MercadoPagoController;
use Equidna\StagHerd\Http\Controllers\PaymentController;
use Equidna\StagHerd\Http\Controllers\PaymentOperationController;
use Equidna\StagHerd\Http\Controllers\PayPalController;
use Equidna\StagHerd\Http\Controllers\StripeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api'])
    ->prefix(config('stag-herd.demo.prefix', 'stag-herd/payments'))
    ->name('stag-herd.payments.')
    ->group(function (): void {
        if (config('stag-herd.demo.enabled', false)) {
            Route::get('/', [PaymentController::class, 'index'])->name('index');
            Route::post('/', [PaymentController::class, 'store'])->name('store');
            Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
            Route::post('/{payment}/lookup', [PaymentOperationController::class, 'lookup'])->name('lookup');
            Route::post('/{payment}/cancel', [PaymentOperationController::class, 'cancel'])->name('cancel');
            Route::post('/{payment}/refund', [PaymentOperationController::class, 'refund'])->name('refund');
            Route::post('/{payment}/sync', [PaymentOperationController::class, 'sync'])->name('sync');
            Route::post('/provider/lookup', [PaymentOperationController::class, 'providerLookup'])->name('provider.lookup');
            Route::post('/provider/sync', [PaymentOperationController::class, 'providerSync'])->name('provider.sync');
        }

        if (config('stag-herd.providers.mercado_pago.enabled', false)) {
            Route::post('/mercado-pago/brick', [MercadoPagoController::class, 'processBrick'])->name('mercado-pago.brick');
            Route::post('/mercado-pago/checkout-pro', [MercadoPagoController::class, 'createCheckoutPro'])->name('mercado-pago.checkout-pro');
        }

        if (config('stag-herd.providers.paypal.enabled', false)) {
            Route::prefix('paypal')->name('paypal.')->controller(PayPalController::class)->group(function (): void {
                Route::post('/create', 'createOrder')->name('create');
                Route::post('/capture', 'captureOrder')->name('capture');
            });
        }

        if (config('stag-herd.providers.stripe.enabled', false)) {
            Route::prefix('stripe')->name('stripe.')->controller(StripeController::class)->group(function (): void {
                Route::post('/setup-intent', 'createSetupIntent')->name('setup-intent');
                Route::post('/setup-complete', 'completeSetupIntent')->name('setup-complete');
                Route::post('/tokenized-card', 'processTokenizedCard')->name('tokenized-card');
                Route::post('/apple-pay', 'processApplePay')->name('apple-pay');
                Route::post('/google-pay', 'processGooglePay')->name('google-pay');
                Route::post('/intent', 'createPaymentIntent')->name('intent');
                Route::post('/confirm', 'confirmPaymentIntent')->name('confirm');
            });
        }
    });
