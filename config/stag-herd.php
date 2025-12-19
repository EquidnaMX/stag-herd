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
        // ],
    ],

    // Enable/disable cash payments
    'cash_enabled' => env('CASH_PAYMENT_ENABLED', true),

    // Eloquent Model for Payment persistence (must be configured by host app)
    // Default is null to force explicit configuration and avoid stale defaults.
    'payment_model' => null,

    // Stripe webhook verification
    'stripe' => [
        'enabled' => env('STRIPE_ENABLED', true),
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
    ],

    // PayPal webhook verification
    'paypal' => [
        'enabled' => env('PAYPAL_ENABLED', true),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'sandbox' => (bool) env('PAYPAL_SANDBOX', true),
        'client_id' => env('PAYPAL_KEY'),
        'client_secret' => env('PAYPAL_SECRET'),
        'token_ttl' => (int) env('PAYPAL_TOKEN_TTL', 3000),
    ],

    // Mercado Pago webhook verification
    'mercadopago' => [
        'enabled' => env('MERCADOPAGO_ENABLED', false),
        'secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
    ],

    // Conekta webhook verification
    'conekta' => [
        'enabled' => env('CONEKTA_ENABLED', false),
        'secret' => env('CONEKTA_WEBHOOK_SECRET'),
    ],

    // Kueski Pay webhook verification
    'kueski' => [
        'enabled' => env('KUESKI_ENABLED', false),
        'webhook_secret' => env('KUESKI_WEBHOOK_SECRET'),
    ],

    // Openpay webhook verification
    'openpay' => [
        'enabled' => env('OPENPAY_ENABLED', false),
        'secret' => env('OPENPAY_WEBHOOK_SECRET'),
        'id' => env('OPENPAY_ID'),
    ],

    // Clip payment configuration
    'clip' => [
        'enabled' => env('CLIP_ENABLED', false),
        'api_key' => env('CLIP_API_KEY'),
    ],

    // Idempotency configuration for webhook processing
    'idempotency_ttl' => (int) env('WEBHOOK_IDEMPOTENCY_TTL', 604800), // default 7 days

    // Rate limiting for webhook endpoints
    'webhook_rate_limit' => (int) env('WEBHOOK_RATE_LIMIT', 60), // requests per minute
    'webhook_rate_decay' => (int) env('WEBHOOK_RATE_DECAY', 1), // decay minutes

    // Audit logging configuration
    'audit_log_channel' => env('STAG_HERD_AUDIT_CHANNEL', 'stack'),
    'audit_log_enabled' => (bool) env('STAG_HERD_AUDIT_ENABLED', true),

    // Payment Fees Configuration
    // Defaults fall back to handler constants if not defined here.
    'fees' => [
        'PAYPAL' => [
            'fixed' => 4,
            'variable' => 0.0395,
        ],
        'STRIPE' => [
            'fixed' => 2.9,
            'variable' => 0.029,
        ],
        // Add other providers as needed
    ],

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
