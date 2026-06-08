<?php

namespace Equidna\StagHerd;

use Equidna\StagHerd\Application\PaymentService;
use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Infrastructure\Persistence\EloquentPaymentRepository;
use Equidna\StagHerd\Infrastructure\Providers\Cash\CashProvider;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\MercadoPagoApiAdapter;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\MercadoPagoProvider;
use Equidna\StagHerd\Infrastructure\Providers\PayPal\PayPalApiAdapter;
use Equidna\StagHerd\Infrastructure\Providers\PayPal\PayPalProvider;
use Equidna\StagHerd\Support\ProviderRegistry;
use Illuminate\Support\ServiceProvider;

class StagHerdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/stag-herd.php',
            'stag-herd'
        );

        $this->registerRepositories();
        $this->registerGateways();
        $this->registerProviders();
        $this->registerServices();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'stag-herd');

        $this->loadRoutesFrom(__DIR__ . '/../routes/payments.php');

        $this->publishes([
            __DIR__ . '/../config/stag-herd.php' => config_path('stag-herd.php'),
        ], 'stag-herd-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'stag-herd-migrations');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/stag-herd'),
        ], 'stag-herd-views');

        $this->publishes([
            __DIR__ . '/../resources/js' => resource_path('js/stag-herd'),
        ], 'stag-herd-assets');
    }

    private function registerRepositories(): void
    {
        $this->app->bind(PaymentRepository::class, function ($app) {
            $repository = config('stag-herd.repositories.payments');

            if ($repository) {
                return $app->make($repository);
            }

            return $app->make(EloquentPaymentRepository::class);
        });
    }

    private function registerGateways(): void
    {
        $this->app->bind(
            MercadoPagoGateway::class,
            MercadoPagoApiAdapter::class,
        );

        $this->app->bind(
            PayPalGateway::class,
            PayPalApiAdapter::class,
        );
    }

    private function registerProviders(): void
    {
        $this->app->singleton(ProviderRegistry::class, function ($app) {
            $registry = new ProviderRegistry();

            if (config('stag-herd.providers.cash.enabled', true)) {
                $registry->register($app->make(CashProvider::class));
            }

            if (config('stag-herd.providers.mercado_pago.enabled', false)) {
                $registry->register($app->make(MercadoPagoProvider::class));
            }

            if (config('stag-herd.providers.paypal.enabled', false)) {
                $registry->register($app->make(PayPalProvider::class));
            }

            return $registry;
        });
    }

    private function registerServices(): void
    {
        $this->app->singleton(PaymentService::class);
    }
}
