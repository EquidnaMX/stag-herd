<?php

return [
    // Prefix for the webhook routes (default: stag-herd)
    'route_prefix' => env('STAG_HERD_ROUTE_PREFIX', 'stag-herd'),

    // Host-defined payment methods (optional - add your custom handlers here)
    // Package handlers are registered automatically by StagHerdServiceProvider
    'custom_methods' => [
        // Example: Add your custom payment methods here
        // 'CLIENT_CREDIT' => [
        //     'handler' => 'App\Classes\Payment\Handlers\ClientCreditHandler',
        //     'description' => 'Linea de crédito cliente',
        //     'enabled' => true,
        //     'fee' => [
        //         'fixed' => 0,
        //         'variable' => 0,
        //     ],
        // ],
    ],

    // Eloquent Model for Payment persistence (must be configured by host app)
    // Default is null to force explicit configuration and avoid stale defaults.
    'payment_model' => env('STAG_HERD_PAYMENT_MODEL'),

    // Stripe webhook verification
    'stripe' => [
        'enabled' => env('STRIPE_ENABLED', true),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'api_secret' => env('STRIPE_SECRET'),
        'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        'fee' => [
            'fixed' => 2.9,
            'variable' => 0.029,
        ],
    ],

    // PayPal webhook verification
    'paypal' => [
        'enabled' => env('PAYPAL_ENABLED', true),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'sandbox' => (bool) env('PAYPAL_SANDBOX', true),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'token_ttl' => (int) env('PAYPAL_TOKEN_TTL', 3000),
        'return_url' => env('PAYPAL_RETURN_ROUTE'),
        'cancel_url' => env('PAYPAL_CANCEL_ROUTE'),
        'fee' => [
            'fixed' => 4,
            'variable' => 0.0395,
        ],
    ],

    // Mercado Pago webhook verification
    'mercadopago' => [
        'enabled' => env('MERCADOPAGO_ENABLED', false),
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
        'return_url' => env('MERCADOPAGO_RETURN_ROUTE', 'stag-herd.mercadopago.confirm'),
        'fee' => [
            'fixed' => 0,
            'variable' => 0,
        ],
    ],

    // Conekta webhook verification
    'conekta' => [
        'enabled' => env('CONEKTA_ENABLED', false),
        'webhook_public_key' => env('CONEKTA_WEBHOOK_PUBLIC_KEY'),
        'api_secret' => env('CONEKTA_API_SECRET'),
        'fee' => [
            'fixed' => 0,
            'variable' => 0,
        ],
    ],

    // Openpay webhook verification
    'openpay' => [
        'enabled' => env('OPENPAY_ENABLED', false),
        'merchant_id' => env('OPENPAY_MERCHANT_ID'),
        'private_key' => env('OPENPAY_PRIVATE_KEY'),
        'sandbox' => (bool) env('OPENPAY_SANDBOX', true),
        'webhook_secret' => env('OPENPAY_WEBHOOK_SECRET'),
        'fee' => [
            'fixed' => 0,
            'variable' => 0,
        ],
    ],

    // Clip payment configuration
    'clip' => [
        'enabled' => env('CLIP_ENABLED', false),
        'api_key' => env('CLIP_API_KEY'),
        'api_base_url' => env('CLIP_API_BASE_URL', 'https://api-gw.payclip.com'),
        'currency' => env('CLIP_CURRENCY', 'MXN'),
        'success_url' => env('CLIP_SUCCESS_URL'),
        'error_url' => env('CLIP_ERROR_URL'),
        'default_url' => env('CLIP_DEFAULT_URL'),
        'webhook_url' => env('CLIP_WEBHOOK_URL'),
        'fee' => [
            'fixed' => 0,
            'variable' => 0,
        ],
    ],

    // Idempotency configuration for webhook processing
    'idempotency_ttl' => (int) env('WEBHOOK_IDEMPOTENCY_TTL', 604800), // default 7 days

    // Rate limiting for webhook endpoints
    'webhook_rate_limit' => (int) env('WEBHOOK_RATE_LIMIT', 60), // requests per minute
    'webhook_rate_decay' => (int) env('WEBHOOK_RATE_DECAY', 1), // decay minutes

    // Audit logging configuration
    'audit_log_channel' => env('STAG_HERD_AUDIT_CHANNEL', 'stack'),
    'audit_log_enabled' => (bool) env('STAG_HERD_AUDIT_ENABLED', true),

    // Cleanup and maintenance routines for payment records
    'cleanup' => [
        'enabled' => (bool) env('STAG_HERD_CLEANUP_ENABLED', true),
        'cron' => env('STAG_HERD_CLEANUP_CRON', '0 3 * * *'),
        'timestamp_column' => env('STAG_HERD_PAYMENT_TIMESTAMP_COLUMN', 'dt_registration'),
        'stale_pending_days' => (int) env('STAG_HERD_STALE_PENDING_DAYS', 14),
        'stale_status' => env('STAG_HERD_STALE_PENDING_STATUS', 'CANCELED'),
        'revalidate' => [
            'enabled' => (bool) env('STAG_HERD_REVALIDATE_ENABLED', false),
            'lookback_hours' => (int) env('STAG_HERD_REVALIDATE_LOOKBACK_HOURS', 24),
            'methods' => [
                'MERCADOPAGO',
                'PAYPAL',
                'OPENPAY',
                'GOOGLEPAY',
                'CLIP',
            ],
        ],
    ],
];
