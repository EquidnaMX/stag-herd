# StagHerd Payment Processing

Multi-provider payment webhook verification and processing for Laravel applications.

## Features

- **Multi-Provider Support**: PayPal, Stripe (Google Pay), Mercado Pago, Openpay, Clip, Conekta
- **Webhook Verification**: Cryptographic signature validation for all providers
- **Idempotency**: Prevents duplicate webhook processing
- **Event-Driven**: Dispatches Laravel events for payment state changes
- **Configurable**: Extensible handler system via configuration
- **Decoupled**: Clean contracts for integration with any Laravel application

## Installation

```bash
composer require equidna/stag-herd
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=stag-herd-config
```

### Environment Variables

Use `.env.example` as the package template and see `docs/environment.md` for local, sandbox, and production rollout notes. Add the relevant values to the host application's `.env`:

```env
STAG_HERD_ROUTE_PREFIX=stag-herd
STAG_HERD_PAYMENT_MODEL=App\Models\Payment

# Enable/Disable Payment Methods
STRIPE_ENABLED=true
PAYPAL_ENABLED=true
MERCADOPAGO_ENABLED=false
CONEKTA_ENABLED=false
OPENPAY_ENABLED=false
CLIP_ENABLED=false

# Webhook Secrets
STRIPE_WEBHOOK_SECRET=whsec_...
PAYPAL_WEBHOOK_ID=...
MERCADOPAGO_WEBHOOK_SECRET=...
CONEKTA_WEBHOOK_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----..."
OPENPAY_WEBHOOK_SECRET=...

# API Credentials
STRIPE_SECRET=...
MERCADOPAGO_ACCESS_TOKEN=...
CONEKTA_API_SECRET=...
OPENPAY_MERCHANT_ID=...
OPENPAY_PRIVATE_KEY=...
OPENPAY_SANDBOX=true

# PayPal Configuration
PAYPAL_SANDBOX=true
PAYPAL_CLIENT_ID=...
PAYPAL_CLIENT_SECRET=...

# Clip Configuration
CLIP_API_KEY=...
CLIP_API_BASE_URL=https://api-gw.payclip.com
CLIP_CURRENCY=MXN
CLIP_SUCCESS_URL=https://your.app/payments/success
CLIP_ERROR_URL=https://your.app/payments/error
CLIP_DEFAULT_URL=https://your.app/payments/cancel
CLIP_WEBHOOK_URL=https://your.app/stag-herd/clip
```

### Payment Model

Configure your Payment Eloquent model in `config/stag-herd.php`:

```php
'payment_model' => 'App\Models\Finance\Payment',
```

### Custom Payment Methods

Payment methods from the package are registered automatically. To add custom handlers, configure them in `config/stag-herd.php`:

```php
'custom_methods' => [
    'CLIENT_CREDIT' => [
        'handler' => 'App\Classes\Payment\Handlers\ClientCreditHandler',
        'description' => 'Linea de crédito cliente',
        'enabled' => true,
    ],
    'GIFT' => [
        'handler' => 'App\Classes\Payment\Handlers\GiftHandler',
        'description' => 'Regalo',
        'enabled' => true,
    ],
],
```

## Usage

### Requesting a Payment

```php
use Equidna\StagHerd\Payment\Payment;

$payment = Payment::request(
    amount: 100.00,
    method: 'PAYPAL',
    order: $order, // Must implement PayableOrder contract
    method_data: (object) ['return_url' => 'https://...']
);

// Access payment link
$paymentLink = $payment->getPaymentModel()->link;
```

## Method Data

Below are the expected `method_data` fields per provider when creating a payment.

### Mercado Pago

```php
method_data: (object) [
    'token' => '<card_token>',
    'payment_method_id' => 'visa',
    'payer_email' => 'buyer@email.com', // optional if order has client email
    'installments' => 1,
    'issuer_id' => null,
]
```

### Conekta

```php
// OXXO
method_data: (object) [
    'payment_method' => 'oxxo_cash', // or 'oxxo'
    'payer_email' => 'buyer@email.com',
    'payer_name' => 'Buyer Name',
]

// Card
method_data: (object) [
    'payment_method' => 'card',
    'token' => '<card_token>',
    'payer_email' => 'buyer@email.com',
    'payer_name' => 'Buyer Name',
]
```

### PayPal

```php
// Request (order creation)
method_data: (object) [
    'return_url' => 'https://your.app/return',
    'cancel_url' => 'https://your.app/cancel',
]

// After approval (webhook/validation), the package stores:
// method_data.capture_id = '<paypal_capture_id>'
// This capture_id is used for refunds.
```

## Maintenance & Cleanup

Run the scheduled cleanup to remove orphan payments and close stale pending records:

```bash
php artisan stag-herd:payments:clean
```

- The package schedules this command daily at 03:00 (cron `0 3 * * *`) when `STAG_HERD_CLEANUP_ENABLED=true`.
- Use `--revalidate` to force revalidation of recent pending payments; `--skip-revalidate` suppresses revalidation even if enabled in config.
- Configure behavior via `config/stag-herd.php` (`cleanup` section): cron expression, lookback window for revalidation, and stale pending age (default 14 days).

### Implementing Contracts

Your application must implement the required contracts:

#### PayableOrder

```php
use Equidna\StagHerd\Contracts\PayableOrder;

class Order implements PayableOrder
{
    public function getID(): int|string { return $this->id_order; }
    public function getClient(): PayableClient { return $this->client; }
    public function getStatus(): string { return $this->status; }
    public function processPayment($payment): void { /* Update order */ }
    public static function fromID(int|string $id): static { /* Load order */ }
}
```

#### PayableClient

```php
use Equidna\StagHerd\Contracts\PayableClient;

class Client implements PayableClient
{
    public function getID(): int|string { return $this->id_client; }
    public function getName(): string { return $this->name; }
    public function getEmail(): string { return $this->email; }
}
```

#### PaymentContextProvider (Optional)

Bind a custom context provider if you need to control `uri_registrar` and `registrar_type` values.

```php
use Equidna\StagHerd\Contracts\PaymentContextProvider;

app()->bind(PaymentContextProvider::class, function () {
    return new class implements PaymentContextProvider {
        public function getExecutorUri(): ?string { return request()->fullUrl(); }
        public function getExecutorType(): ?string { return 'http'; }
    };
});
```

### Custom Payment Handlers

Extend `PaymentHandler` for custom payment methods:

```php
namespace App\Payment\Handlers;

use Equidna\StagHerd\Data\PaymentResult;
use Equidna\StagHerd\Payment\Handlers\PaymentHandler;

class CustomHandler extends PaymentHandler
{
    public const PAYMENT_METHOD = 'CUSTOM_METHOD';

    public function requestPayment(): PaymentResult
    {
        // Custom logic
        return PaymentResult::pending(
            method_id: 'custom-' . uniqid(),
            reason: 'Always PENDING'
        );
    }

    protected function validatePayment($paymentModel): PaymentResult
    {
        parent::validatePayment($paymentModel);

        // Validation logic
        return PaymentResult::success(
            result: 'APPROVED',
            method_id: $paymentModel->method_id
        );
    }
}
```

### Gateway Contracts

Provider adapters are bound behind contracts so host applications can replace them for tests, sandbox wrappers, tracing, or custom retry policies:

```php
use Equidna\StagHerd\Contracts\ClipGateway;
use App\Payments\InstrumentedClipGateway;

app()->bind(ClipGateway::class, InstrumentedClipGateway::class);
```

Available gateway contracts:

- `ClipGateway`
- `ConektaGateway`
- `MercadoPagoGateway`
- `OpenpayGateway`
- `PayPalGateway`
- `StripeGateway`

### Payment State Machine

`Payment::applyResult()` enforces payment state transitions centrally:

- `PENDING` can resolve to `APPROVED`, `REJECTED`, `DECLINED`, `CANCELED`, `REFUNDED`, or `CHARGEBACK`.
- `APPROVED` can move to `REFUNDED`, `CHARGEBACK`, or `CANCELED`.
- `REJECTED`, `DECLINED`, `CANCELED`, `REFUNDED`, and `CHARGEBACK` are terminal except for idempotent repeats of the same status.

Invalid transitions are logged and ignored, for example `REJECTED -> APPROVED`.

## Webhook Routes

Webhooks are automatically registered with the configured prefix (default: `stag-herd`):

- `POST /stag-herd/paypal`
- `POST /stag-herd/mercadopago`
- `POST /stag-herd/conekta`
- `POST /stag-herd/openpay`
- `POST /stag-herd/googlepay`
- `POST /stag-herd/clip`

## Webhooks

This package verifies webhook signatures for each provider using their official schemes. Ensure you send the raw request body to the verifier and configure secrets.

### Stripe

- Header: `Stripe-Signature: t=<timestamp>,v1=<signature>`
- Signature: `HMAC-SHA256("<timestamp>.<raw_body>", STRIPE_WEBHOOK_SECRET)`
- Tolerance: default 300 seconds; events outside tolerance are rejected.
- Idempotency: the event `id` is deduplicated to prevent reprocessing.

### Mercado Pago

- Headers: `X-Signature`, `X-Request-Id`
- Signature parts: `ts=<unix_ts>,v1=<signature>`
- Manifest: `id:<data.id>;request-id:<X-Request-Id>;ts:<ts>;`
- Signature: `HMAC-SHA256(manifest, MERCADOPAGO_WEBHOOK_SECRET)`
- `data.id` is taken from query/body JSON `data.id` or `id`.

### Conekta

- Header: `Digest: <base64_rsa_signature>` (the implementation also accepts the `sha-256=<signature>` prefix).
- Signature: RSA SHA-256 verification of the raw UTF-8 body using the webhook public key generated by Conekta.
- Public key source: `config('stag-herd.conekta.webhook_public_key')` (populate from `CONEKTA_WEBHOOK_PUBLIC_KEY`).
- Event id: body JSON `id` when present.

### Clip

- Clip Checkout webhook notifications are reconciled by `payment_request_id`.
- The webhook payload is not trusted as payment state. The package re-queries Clip Checkout (`GET /checkout/{payment_request_id}`) before applying `APPROVED`, `REJECTED`, `CANCELED`, or `PENDING`.
- Configure `CLIP_WEBHOOK_URL` or let the package use the `stag-herd.clip` route when creating Checkout links.

### Openpay

- Header: `Verification-Signature: t=<timestamp>,v1=<signature>` (or `Signature-Digest`)
- Signature: `HMAC-SHA256("<timestamp>.<raw_body>", OPENPAY_WEBHOOK_SECRET)`
- Event id: body JSON `event_id` or `id`.

### Idempotency

- `WebhookVerifier::checkIdempotency(eventId, provider, ttl)` returns true for duplicates and stores new events via cache.
- Configure a persistent cache backend in production to retain deduplication keys.

### Examples

```http
POST /webhook HTTP/1.1
Stripe-Signature: t=1710000000,v1=abcdef...
Content-Type: application/json

{"id":"evt_123","type":"charge.succeeded"}
```

```http
POST /webhook HTTP/1.1
X-Signature: ts=1710000000,v1=abcdef...
X-Request-Id: req-123
Content-Type: application/json

{"data":{"id":"123"}}
```

```http
POST /webhook HTTP/1.1
Digest: sha-256=abc123base64=
Content-Type: application/json

{"id":"evt_123","type":"charge.paid"}
```

```http
POST /webhook HTTP/1.1
Verification-Signature: t=1710000000,v1=abcdef...
Content-Type: application/json

{"event_id":"evt_op","id":"op_123"}
```

## Events

Listen to payment events in your application:

```php
use Equidna\StagHerd\Events\PaymentApproved;
use Equidna\StagHerd\Events\PaymentCanceled;
use Equidna\StagHerd\Events\PaymentChargeback;
use Equidna\StagHerd\Events\PaymentLinkCreated;
use Equidna\StagHerd\Events\PaymentRefunded;
use Equidna\StagHerd\Events\PaymentRejected;
use Illuminate\Support\Facades\Event;

Event::listen(PaymentApproved::class, function (PaymentApproved $event) {
    $payment = $event->payment;
    // Handle approved payment
});

Event::listen(PaymentLinkCreated::class, function (PaymentLinkCreated $event) {
    $payment = $event->payment;
    $link = $event->link;
    // Notify client with payment link
});
```

## Testing

```bash
composer test
```

For provider sandbox validation, follow `docs/sandbox-test-matrix.md`.

## Static Analysis

```bash
composer phpstan
```

## License

MIT License. See LICENSE file for details.
