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
        /*
     * Repository principal del core.
     *
     * Sirve para guardar, buscar y actualizar pagos.
     */
        'payments' => null,

        /*
     * Repository usado solamente por la UI/demo.
     *
     * Si el host usa tablas propias y quiere que la UI del paquete las muestre,
     * puede registrar aquí un repository específico para display.
     */
        'payment_display' => null,

        /*
     * Para cuando agregues persistencia de webhooks.
     */
        'webhooks' => null,
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

        'paypal' => [
            'enabled' => env('STAG_HERD_PAYPAL_ENABLED', true),

            'methods' => [
                'paypal' => true,
            ],

            'credentials' => [
                'client_id' => env('PAYPAL_CLIENT_ID'),
                'secret' => env('PAYPAL_SECRET'),
            ],

            'http' => [
                'base_uri' => env('PAYPAL_BASE_URI', 'https://api-m.sandbox.paypal.com'),
                'timeout' => env('PAYPAL_TIMEOUT', 15),
            ],
        ],
    ],
];
