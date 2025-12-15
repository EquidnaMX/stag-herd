# StagHerd Payment Processing

Multi-provider payment webhook verification and processing for Laravel applications.

## Features

- **Multi-Provider Support**: PayPal, Stripe (Google Pay), Mercado Pago, Openpay, Clip, Conekta, Kueski Pay
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

Add the following to your `.env`:

```env
# Enable/Disable Payment Methods
STRIPE_ENABLED=true
PAYPAL_ENABLED=true
MERCADOPAGO_ENABLED=false
CONEKTA_ENABLED=false
KUESKI_ENABLED=false
OPENPAY_ENABLED=false
CLIP_ENABLED=false
CASH_PAYMENT_ENABLED=true

# Webhook Secrets
STRIPE_WEBHOOK_SECRET=whsec_...
PAYPAL_WEBHOOK_ID=...
MERCADOPAGO_WEBHOOK_SECRET=...
CONEKTA_WEBHOOK_SECRET=...
KUESKI_WEBHOOK_SECRET=...
OPENPAY_WEBHOOK_SECRET=...

# PayPal Configuration
PAYPAL_SANDBOX=true
PAYPAL_KEY=...
PAYPAL_SECRET=...

# Clip Configuration
CLIP_API_KEY=...
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

### Custom Payment Handlers

Extend `PaymentHandler` for custom payment methods:

```php
namespace App\Payment\Handlers;

use Equidna\StagHerd\Payment\Handlers\PaymentHandler;
use stdClass;

class CustomHandler extends PaymentHandler
{
    public const PAYMENT_METHOD = 'CUSTOM_METHOD';

    public function requestPayment(): stdClass
    {
        $result = parent::requestPayment();

        // Custom logic
        $result->method_id = 'custom-' . uniqid();
        $result->result = 'PENDING';

        return $result;
    }

    protected function validatePayment($paymentModel): stdClass
    {
        $result = parent::validatePayment($paymentModel);

        // Validation logic
        $result->result = 'APPROVED';

        return $result;
    }
}
```

## Webhook Routes

Webhooks are automatically registered with the configured prefix (default: `stag-herd`):

- `POST /stag-herd/stripe`
- `POST /stag-herd/paypal`
- `POST /stag-herd/mercado-pago`
- `POST /stag-herd/conekta`
- `POST /stag-herd/kueski`
- `POST /stag-herd/openpay`

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

- Header: `Digest: sha-256=<base64_hmac>`
- Signature: `base64(HMAC-SHA256(raw_body, CONEKTA_WEBHOOK_SECRET))`
- Secret source: `config('stag-herd.conekta.secret')` (populate from `CONEKTA_WEBHOOK_SECRET`).
- Event id: body JSON `id` when present.

### Kueski Pay

- Headers: `X-Kueski-Signature`, `X-Kueski-Timestamp`
- Signature: `HMAC-SHA256("<timestamp>" . raw_body, KUESKI_WEBHOOK_SECRET)`
- Event id: body JSON `event_id` or `id`.

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
X-Kueski-Timestamp: 1710000000
X-Kueski-Signature: abcdef...
Content-Type: application/json

{"event_id":"evt_ksk","id":"ksk_123"}
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
use Illuminate\Support\Facades\Event;

Event::listen(PaymentApproved::class, function (PaymentApproved $event) {
    $payment = $event->payment;
    // Handle approved payment
});
```

## Testing

```bash
composer test
```

## Static Analysis

```bash
composer phpstan
```

## License

MIT License. See LICENSE file for details.
