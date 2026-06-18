<?php

use Equidna\StagHerd\Infrastructure\Providers\Cash\CashProvider;
use Equidna\StagHerd\Infrastructure\Providers\Custom\CustomProvider;
use Equidna\StagHerd\Infrastructure\Providers\PayPal\PayPalProvider;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\MercadoPagoProvider;
use Equidna\StagHerd\Infrastructure\Providers\Cash\Handlers\CashPaymentHandler;
use Equidna\StagHerd\Infrastructure\Providers\PayPal\Handlers\PayPalCheckoutHandler;
use Equidna\StagHerd\Infrastructure\Providers\MercadoPago\Handlers\MercadoPagoCardHandler;

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
            'middleware' => ['web'],
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
                'public_key' => env('MERCADO_PAGO_PUBLIC_KEY'),
                'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET'),
            ],

            /*
             * orders = flujo recomendado para Card Brick. Regresa ORD... y payment_id.
             * payments = flujo directo/legacy /v1/payments.
             */
            'checkout_flow' => env('MERCADO_PAGO_CHECKOUT_FLOW', 'orders'),

            'orders' => [
                'processing_mode' => env('MERCADO_PAGO_PROCESSING_MODE', 'automatic'),
                'capture_mode' => env('MERCADO_PAGO_CAPTURE_MODE', 'automatic_async'),
                'config' => [
                    'online' => [
                        'transaction_security' => [
                            'validation' => env('MERCADO_PAGO_SECURITY_VALIDATION', 'on_fraud_risk'),
                            'liability_shift' => env('MERCADO_PAGO_LIABILITY_SHIFT', 'required'),
                        ],
                    ],
                ],
            ],

            'http' => [
                'base_uri' => env('MERCADO_PAGO_BASE_URI', 'https://api.mercadopago.com'),
                'timeout' => 15,
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
                'client_id' => env('PAYPAL_CLIENT_ID'),
                'secret' => env('PAYPAL_SECRET'),
            ],

            'http' => [
                'base_uri' => env('PAYPAL_BASE_URI', 'https://api-m.sandbox.paypal.com'),
                'timeout' => 15,
            ],
        ],
    ],
];
