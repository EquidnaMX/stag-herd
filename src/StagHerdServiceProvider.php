<?php

namespace Equidna\StagHerd;

use Equidna\StagHerd\Infrastructure\Persistence\EloquentPaymentDisplayRepository;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\MercadoPagoApiAdapter;
use Equidna\StagHerd\Infrastructure\Webhooks\RedisWebhookIdempotencyStore;
use Equidna\StagHerd\Infrastructure\Persistence\EloquentPaymentRepository;
use Equidna\StagHerd\Infrastructure\Providers\PayPal\PayPalApiAdapter;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\StripeApiAdapter;
use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Support\PaymentMethodHandlerRegistry;
use Equidna\StagHerd\Contracts\PaymentDisplayRepository;
use Equidna\StagHerd\Contracts\WebhookIdempotencyStore;
use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Contracts\PaymentMethodHandler;
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Application\PaymentService;
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
        $this->registerWebhooks();
        $this->registerProviderMethodHandlers();
        $this->registerProviders();
        $this->registerServices();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'stag-herd');

        $this->loadRoutesFrom(__DIR__ . '/../routes/payments.php');

        if (config('stag-herd.webhooks.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');
        }

        $this->publishes([
            __DIR__ . '/../config/stag-herd.php' => config_path('stag-herd.php'),
        ], 'stag-herd-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'stag-herd-migrations');

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

        $this->app->bind(PaymentDisplayRepository::class, function ($app) {
            $repository = config('stag-herd.repositories.payment_display');

            if ($repository) {
                return $app->make($repository);
            }

            return $app->make(EloquentPaymentDisplayRepository::class);
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

        $this->app->bind(
            StripeGateway::class,
            StripeApiAdapter::class,
        );
    }

    private function registerWebhooks(): void
    {
        $this->app->bind(
            WebhookIdempotencyStore::class,
            RedisWebhookIdempotencyStore::class,
        );
    }

    private function registerProviderMethodHandlers(): void
    {
        foreach (config('stag-herd.providers', []) as $providerName => $providerConfig) {
            $this->app->singleton(
                $this->methodRegistryKey((string) $providerName),
                function ($app) use ($providerName, $providerConfig) {
                    $registry = new PaymentMethodHandlerRegistry();

                    foreach (($providerConfig['methods'] ?? []) as $method => $methodConfig) {
                        if (! ($methodConfig['enabled'] ?? false)) {
                            continue;
                        }

                        $handlerClass = $methodConfig['handler'] ?? null;

                        if (! $handlerClass) {
                            continue;
                        }

                        $handler = $app->make($handlerClass);

                        if (! $handler instanceof PaymentMethodHandler) {
                            throw new \RuntimeException(sprintf(
                                'Payment method handler [%s] for provider [%s] and method [%s] must implement [%s].',
                                $handlerClass,
                                $providerName,
                                $method,
                                PaymentMethodHandler::class,
                            ));
                        }

                        $registry->register($handler);
                    }

                    return $registry;
                }
            );
        }
    }

    private function registerProviders(): void
    {
        $this->app->singleton(ProviderRegistry::class, function ($app) {
            $registry = new ProviderRegistry();

            foreach (config('stag-herd.providers', []) as $providerName => $providerConfig) {
                if (! ($providerConfig['enabled'] ?? false)) {
                    continue;
                }

                $providerClass = $providerConfig['provider'] ?? null;

                if (! $providerClass) {
                    continue;
                }

                $methodRegistry = $app->make(
                    $this->methodRegistryKey((string) $providerName)
                );

                $provider = $app->makeWith($providerClass, [
                    'handlers' => $methodRegistry,
                ]);

                $registry->register($provider);
            }

            return $registry;
        });
    }

    private function registerServices(): void
    {
        $this->app->singleton(PaymentService::class);
    }

    private function methodRegistryKey(string $providerName): string
    {
        return 'stag-herd.provider.' . $providerName . '.method-handlers';
    }
}
