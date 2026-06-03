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
        'webhooks' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Para Fase 1 solo necesitamos cash.
    |
    */

    'providers' => [
        'cash' => [
            'enabled' => env('STAG_HERD_CASH_ENABLED', true),
            'methods' => [
                'cash' => true,
            ],
        ],
        'mercado_pago' => [
            'enabled' => env('STAG_HERD_MERCADO_PAGO_ENABLED', true),

            'methods' => [
                'card' => true,
            ],

            'credentials' => [
                'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN'),
                'public_key' => env('MERCADO_PAGO_PUBLIC_KEY'),
                'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET'),
            ],

            'http' => [
                'base_uri' => env('MERCADO_PAGO_BASE_URI', 'https://api.mercadopago.com'),
                'timeout' => env('MERCADO_PAGO_TIMEOUT', 15),
            ],
        ],
    ],
];
