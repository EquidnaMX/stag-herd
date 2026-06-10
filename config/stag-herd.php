<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Repositories
    |--------------------------------------------------------------------------
    |
    | Si el host no define repositorios, el paquete usa sus repositorios internos.
    | Si el host define una clase, se usa esa implementación.
    |
    */

    'repositories' => [
        'payments' => null,
        'payment_display' => null,
        'webhooks' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo UI
    |--------------------------------------------------------------------------
    |
    | La demo queda apagada por defecto para no contaminar al host.
    | Solo debe prenderse en proyectos de prueba o sandbox.
    |
    */

    'demo' => [
        'enabled' => false,
        'middleware' => ['web'],
        'prefix' => 'stag-herd/payments',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    */

    'webhooks' => [
        'routes' => [
            'enabled' => true,
            'prefix' => 'stag-herd/webhooks',
            'middleware' => ['api'],
        ],

        'idempotency' => [
            'driver' => 'redis',
            'ttl_seconds' => 86400,
            'prefix' => 'stag-herd:webhooks',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'custom' => [
            'provider' => Equidna\StagHerd\Infrastructure\Providers\Custom\CustomProvider::class,
            'enabled' => false,

            'methods' => [
                //
            ],
        ],

        'cash' => [
            'provider' => Equidna\StagHerd\Infrastructure\Providers\Cash\CashProvider::class,
            'enabled' => true,

            'methods' => [
                'cash' => [
                    'enabled' => true,
                    'label' => 'Cash',
                ],
            ],
        ],

        'mercado_pago' => [
            'provider' => Equidna\StagHerd\Infrastructure\Providers\MercadoPago\MercadoPagoProvider::class,
            'enabled' => false,

            'methods' => [
                'card' => [
                    'enabled' => true,
                    'label' => 'Tarjeta',
                ],
            ],

            'credentials' => [
                'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN'),
                'public_key' => env('MERCADO_PAGO_PUBLIC_KEY'),
                'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET'),
            ],

            'http' => [
                'base_uri' => env('MERCADO_PAGO_BASE_URI', 'https://api.mercadopago.com'),
                'timeout' => 15,
            ],
        ],

        'paypal' => [
            'provider' => Equidna\StagHerd\Infrastructure\Providers\PayPal\PayPalProvider::class,
            'enabled' => false,

            'methods' => [
                'paypal' => [
                    'enabled' => true,
                    'label' => 'PayPal',
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
