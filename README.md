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
