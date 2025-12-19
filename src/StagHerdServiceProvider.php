<?php

/**
 * Laravel service provider for StagHerd payment processing package.
 *
 * Registers configuration, routes, payment handlers, and binds the PaymentRepository implementation.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd;

use Equidna\StagHerd\Console\Commands\PaymentsCleanupCommand;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Payment\Handlers\ClipHandler;
use Equidna\StagHerd\Payment\Handlers\ConektaHandler;
use Equidna\StagHerd\Payment\Handlers\GooglePayHandler;
use Equidna\StagHerd\Payment\Handlers\KueskiPayHandler;
use Equidna\StagHerd\Payment\Handlers\MercadoPagoHandler;
use Equidna\StagHerd\Payment\Handlers\OpenpayHandler;
use Equidna\StagHerd\Payment\Handlers\PaymentHandler;
use Equidna\StagHerd\Payment\Handlers\PayPalHandler;
use Equidna\StagHerd\Repositories\EloquentPaymentRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class StagHerdServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes(
            [
                __DIR__ . '/../config/stag-herd.php' => config_path('stag-herd.php'),
            ],
            'stag-herd-config'
        );

        $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');

        // Configure webhook rate limiter
        RateLimiter::for(
            'webhook',
            function () {
                return Limit::perMinute(config('stag-herd.webhook_rate_limit', 60))
                    ->by(request()->ip())
                    ->response(fn() => response()->json(['error' => 'Too many requests'], 429));
            }
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                PaymentsCleanupCommand::class,
            ]);

            $this->scheduleCleanup();
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/stag-herd.php',
            'stag-herd'
        );

        // Register package-provided payment handlers after config is merged
        $this->registerPackageHandlers();

        // Merge custom handlers from config
        $this->mergeCustomHandlers();

        // Bind PaymentRepository - only if not already bound by host
        $this->app->bindIf(
            PaymentRepository::class,
            EloquentPaymentRepository::class
        );
    }

    /**
     * Register payment handlers provided by the package.
     */
    private function registerPackageHandlers(): void
    {
        $packageHandlers = [
            'PAYPAL' => [
                'handler' => PayPalHandler::class,
                'description' => 'PayPal',
                'enabled' => config('stag-herd.paypal.enabled', true),
            ],
            'MERCADOPAGO' => [
                'handler' => MercadoPagoHandler::class,
                'description' => 'Mercado pago',
                'enabled' => config('stag-herd.mercadopago.enabled', false),
            ],
            'OPENPAY' => [
                'handler' => OpenpayHandler::class,
                'description' => 'Openpay',
                'enabled' => config('stag-herd.openpay.enabled', false),
            ],
            'CLIP' => [
                'handler' => ClipHandler::class,
                'description' => 'Clip',
                'enabled' => config('stag-herd.clip.enabled', false),
            ],
            'GOOGLEPAY' => [
                'handler' => GooglePayHandler::class,
                'description' => 'Google Pay',
                'enabled' => config('stag-herd.stripe.enabled', true),
            ],
            'CONEKTA' => [
                'handler' => ConektaHandler::class,
                'description' => 'Conekta (OXXO)',
                'enabled' => config('stag-herd.conekta.enabled', false),
            ],
            'KUESKIPAY' => [
                'handler' => KueskiPayHandler::class,
                'description' => 'Kueski Pay',
                'enabled' => config('stag-herd.kueski.enabled', false),
            ],
            'BASE' => [
                'handler' => PaymentHandler::class,
                'description' => 'Base',
                'enabled' => false,
            ],
            'CASH' => [
                'handler' => PaymentHandler::class,
                'description' => 'Efectivo',
                'enabled' => config('stag-herd.cash_enabled', true),
            ],
        ];

        config(['stag-herd.methods' => $packageHandlers]);
    }

    /**
     * Merge custom payment handlers defined in config.
     */
    private function mergeCustomHandlers(): void
    {
        $customHandlers = config('stag-herd.custom_methods', []);

        if (!empty($customHandlers)) {
            $methods = config('stag-herd.methods', []);
            $mergedMethods = array_merge(
                $methods,
                $customHandlers
            );
            config(['stag-herd.methods' => $mergedMethods]);
        }
    }

    /**
     * Registers the package scheduler for cleanup routines.
     */
    private function scheduleCleanup(): void
    {
        if (!config('stag-herd.cleanup.enabled', true)) {
            return;
        }

        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            $schedule->command('stag-herd:payments:clean')
                ->cron(config('stag-herd.cleanup.cron', '0 0 * * *'))
                ->runInBackground();
        });
    }
}
