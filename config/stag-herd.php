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
    | UI de prueba
    |--------------------------------------------------------------------------
    |
    | Vistas simples para probar el paquete desde un proyecto host fresh.
    | No son parte del checkout final del host, solo ayudan a validar acciones.
    |
    */

    'ui' => [
        'prefix' => env('STAG_HERD_UI_PREFIX', 'stag-herd/payments'),
        'middleware' => ['web'],
        'payments_limit' => 25,
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Aquí se configuran los proveedores de pago disponibles. Cada proveedor puede tener múltiples métodos de pago.
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
