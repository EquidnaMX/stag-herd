<?php

use Equidna\StagHerd\Infrastructure\Providers\Cash\CashProvider;
use Equidna\StagHerd\Infrastructure\Providers\Custom\CustomProvider;
use Equidna\StagHerd\Infrastructure\Providers\PayPal\PayPalProvider;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\MercadoPagoProvider;
use Equidna\StagHerd\Infrastructure\Providers\Cash\Handlers\CashPaymentHandler;
use Equidna\StagHerd\Infrastructure\Providers\PayPal\Handlers\PayPalCheckoutHandler;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\Handlers\MercadoPagoCardHandler;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\MercadoPagoWebhookParser;
use Equidna\StagHerd\Infrastructure\Providers\PayPal\PayPalWebhookParser;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\Handlers\StripeCardHandler;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\StripeProvider;
use Equidna\StagHerd\Infrastructure\Providers\Stripe\StripeWebhookParser;

return [
    'repositories' => [
        'payments' => null,
        'payment_display' => null,
        'webhooks' => null,
    ],

    'demo' => [
        'enabled' => false,
        'middleware' => ['web'],
        'prefix' => 'stag-herd/payments',
    ],

    'webhooks' => [
        'routes' => [
            'enabled' => true,
            'prefix' => 'stag-herd/webhooks',
            'middleware' => ['api'],
        ],

        'idempotency' => [
            'driver' => env('STAG_HERD_WEBHOOK_IDEMPOTENCY_DRIVER', 'redis'),
            'ttl_seconds' => env('STAG_HERD_WEBHOOK_IDEMPOTENCY_TTL', 86400),
            'prefix' => 'stag-herd:webhooks',
        ],
    ],

    'providers' => [
        'custom' => [
            'provider' => CustomProvider::class,
            'enabled' => false,

            'methods' => [
                //
            ],
        ],

        'cash' => [
            'provider' => CashProvider::class,
            'enabled' => true,

            'methods' => [
                'cash' => [
                    'enabled' => true,
                    'label' => 'Cash',
                    'handler' => CashPaymentHandler::class,
                ],
            ],
        ],

        'mercado_pago' => [
            'provider' => MercadoPagoProvider::class,
            'enabled' => false,

            'methods' => [
                'card' => [
                    'enabled' => true,
                    'label' => 'Tarjeta',
                    'handler' => MercadoPagoCardHandler::class,
                ],
            ],

            'credentials' => [
                'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN'),
                'public_key' => env('VITE_MERCADO_PAGO_PUBLIC_KEY'),
                'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET'),
            ],

            'http' => [
                'base_uri' => env('MERCADO_PAGO_BASE_URI', 'https://api.mercadopago.com'),
                'timeout' => 15,
            ],

            'webhooks' => [
                'parser' => MercadoPagoWebhookParser::class,
            ],
        ],

        'paypal' => [
            'provider' => PayPalProvider::class,
            'enabled' => false,

            'methods' => [
                'paypal' => [
                    'enabled' => true,
                    'label' => 'PayPal',
                    'handler' => PayPalCheckoutHandler::class,
                ],
            ],

            'credentials' => [
                'client_id' => env('VITE_PAYPAL_CLIENT_ID'),
                'secret' => env('PAYPAL_CLIENT_SECRET', env('PAYPAL_SECRET')),
                'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
            ],

            'http' => [
                'base_uri' => env('PAYPAL_BASE_URI', 'https://api-m.sandbox.paypal.com'),
                'timeout' => 15,
            ],

            'webhooks' => [
                'parser' => PayPalWebhookParser::class,
            ],
        ],

        'stripe' => [
            'provider' => StripeProvider::class,
            'enabled' => env('STRIPE_ENABLED', false),

            'methods' => [
                'card' => [
                    'enabled' => true,
                    'label' => 'Tarjeta',
                    'handler' => StripeCardHandler::class,
                ],
            ],

            'credentials' => [
                'secret_key' => env('STRIPE_SECRET_KEY'),
                'public_key' => env('VITE_STRIPE_PUBLIC_KEY'),
                'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            ],

            'http' => [
                'base_uri' => env('STRIPE_BASE_URI', 'https://api.stripe.com'),
                'timeout' => 15,
            ],

            'webhooks' => [
                'parser' => StripeWebhookParser::class,
                'tolerance_seconds' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
            ],
        ],
    ],
];
