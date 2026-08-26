# Guía de implementación de StagHerd

Esta guía explica cómo integrar `equidna/stag-herd` en una aplicación Laravel usando la API pública actual del paquete: pagos, proveedores, métodos tokenizados, suscripciones, webhooks, eventos y persistencia configurable.

## 1. Qué resuelve StagHerd

`StagHerd` centraliza la operación de pagos en una capa desacoplada del sistema host.

En vez de que tu aplicación conozca directamente Stripe, PayPal, Mercado Pago o métodos internos, el host trabaja con servicios y contratos comunes:

- `PaymentService` para pagos directos.
- `PaymentMethodService` para tarjetas o métodos guardados.
- `BillingService` para checkout hospedado, suscripciones, portal de cliente y catálogo.
- Repositorios configurables para decidir dónde se persisten los datos.
- Eventos para conectar la lógica del host sin modificar el paquete.

La idea central es simple: el host conserva sus reglas de negocio, y StagHerd se encarga de hablar con el proveedor, normalizar estados y devolver objetos consistentes.

## 2. Cuándo usar cada parte

| Necesidad del host | Usar |
| --- | --- |
| Crear, consultar, confirmar, cancelar o reembolsar pagos | `PaymentService` o facade `StagHerd` |
| Mostrar métodos disponibles en un checkout | `PaymentMethods::getMethods(true)` |
| Guardar y reutilizar tarjetas/métodos de pago | `PaymentMethodService` |
| Crear checkout hospedado de pago único o suscripción | `BillingService::createCheckout()` |
| Consultar o cancelar suscripciones | `BillingService::lookupSubscription()` / `cancelSubscription()` |
| Reaccionar a pagos aprobados, rechazados o reembolsados | Eventos de StagHerd |
| Persistir en tablas propias o API externa | Repositorios personalizados |

## 3. Instalación

Instala el paquete con Composer:

```bash
composer require equidna/stag-herd:dev-dev -W
```

Si lo consumes desde un repositorio local o privado, registra primero el repositorio en el `composer.json` del host:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../stag-herd"
        }
    ]
}
```

Después instala:

```bash
composer require equidna/stag-herd:dev-dev -W
```

## 4. Publicación de configuración y migraciones

Publica la configuración:

```bash
php artisan vendor:publish --tag=stag-herd-config
```

Esto crea:

```text
config/stag-herd.php
```

Si usarás las tablas incluidas por el paquete, publica y ejecuta migraciones:

```bash
php artisan vendor:publish --tag=stag-herd-migrations
php artisan migrate
```

Opcionalmente, si usarás los assets incluidos:

```bash
php artisan vendor:publish --tag=stag-herd-assets
```

## 5. Configuración principal

La configuración vive en `config/stag-herd.php`.

Las secciones que normalmente debes revisar son:

```php
'repositories' => [
    'payments' => null,
    'payment_display' => null,
    'webhooks' => null,
    'payment_methods' => null,
],

'payments' => [
    'routes' => [
        'enabled' => true,
        'prefix' => 'stag-herd/payments',
        'middleware' => ['api'],
    ],
],

'payment_methods' => [
    'routes' => [
        'enabled' => true,
        'prefix' => 'stag-herd/payments/payment-methods',
        'middleware' => ['api'],
    ],
],

'webhooks' => [
    'routes' => [
        'enabled' => true,
        'prefix' => 'stag-herd/webhooks',
        'middleware' => ['api'],
    ],

    'idempotency' => [
        'driver' => env('STAG_HERD_WEBHOOK_IDEMPOTENCY_DRIVER', 'database'),
        'ttl_seconds' => env('STAG_HERD_WEBHOOK_IDEMPOTENCY_TTL', 86400),
        'prefix' => 'stag-herd:webhooks',
    ],
],

'providers' => [
    // cash, custom, mercado_pago, paypal, stripe
],
```

> Nota: la configuración del paquete puede contener entradas internas o experimentales que no forman parte de una integración normal. Para esta guía solo necesitas revisar los repositorios y proveedores estables descritos aquí.

## 6. Variables de entorno por proveedor

Activa solo los proveedores que vayas a usar.

### Mercado Pago

```env
MERCADO_PAGO_ACCESS_TOKEN=
VITE_MERCADO_PAGO_PUBLIC_KEY=
MERCADO_PAGO_WEBHOOK_SECRET=
MERCADO_PAGO_BASE_URI=https://api.mercadopago.com
```

Métodos disponibles en configuración:

- `card`
- `checkout_pro`
- `tokenized_card`

### PayPal

```env
VITE_PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=
PAYPAL_SECRET=
PAYPAL_WEBHOOK_ID=
PAYPAL_ENVIRONMENT=sandbox
PAYPAL_BASE_URI=https://api-m.sandbox.paypal.com
```

`PAYPAL_CLIENT_SECRET` es la variable principal. `PAYPAL_SECRET` existe como fallback de compatibilidad.

Métodos disponibles en configuración:

- `paypal`
- `tokenized_card`

### Stripe

```env
STRIPE_SECRET_KEY=
VITE_STRIPE_PUBLIC_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_API_VERSION=2026-02-25.clover
STRIPE_BASE_URI=https://api.stripe.com
```

Métodos disponibles en configuración:

- `card`
- `apple_pay`
- `google_pay`
- `tokenized_card`
- `spei`

### Webhooks

```env
STAG_HERD_WEBHOOK_IDEMPOTENCY_DRIVER=database
STAG_HERD_WEBHOOK_IDEMPOTENCY_TTL=86400
```

El driver de idempotencia puede usar persistencia local o Redis, según la implementación configurada por el paquete.
Los valores esperados son `database` o `redis`; cualquier valor no reconocido cae a la implementación Eloquent/database.

## 7. Persistencia

StagHerd no obliga al host a usar una tabla específica. Puedes usar las tablas incluidas o conectar tu propia persistencia.

### 7.1 Usar las tablas del paquete

Es la ruta más simple para una integración nueva.

Publica migraciones y ejecuta:

```bash
php artisan vendor:publish --tag=stag-herd-migrations
php artisan migrate
```

Tablas principales incluidas:

| Tabla | Uso |
| --- | --- |
| `stag_herd_payments` | Pagos normalizados |
| `stag_herd_payment_methods` | Métodos/tarjetas guardadas |
| `stag_herd_provider_customers` | Relación auxiliar con clientes del proveedor |
| `stag_herd_billing_resources` | Recursos de billing como checkout sessions y subscriptions |
| `stag_herd_webhook_events` | Idempotencia/procesamiento de webhooks |

Con esta opción puedes dejar los repositorios en `null`:

```php
'repositories' => [
    'payments' => null,
    'payment_display' => null,
    'webhooks' => null,
    'payment_methods' => null,
],
```

El paquete usará sus implementaciones Eloquent internas.

### 7.2 Usar tabla propia del host para pagos

Si tu sistema ya tiene una tabla como `payments`, `transactions` o `order_payments`, implementa:

```php
Equidna\StagHerd\Contracts\PaymentRepository
```

Contrato:

```php
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Domain\Payment;

final class HostPaymentRepository implements PaymentRepository
{
    public function storeFromResult(
        PaymentRequestData $request,
        PaymentResultData $result,
    ): Payment {
        // Create a host payment record and map it to Payment.
    }

    public function find(int|string $id): ?Payment
    {
        // Find by host payment id.
    }

    public function findByProviderPaymentId(
        string $provider,
        string $providerPaymentId,
    ): ?Payment {
        // Find by provider payment id.
    }

    public function findByProviderOrderId(
        string $provider,
        string $providerOrderId,
    ): ?Payment {
        // Find by provider order id.
    }

    public function findByExternalReference(string $externalReference): ?Payment
    {
        // Find by stable host reference, for example ORDER-1001.
    }

    public function updateFromResult(
        Payment $payment,
        PaymentResultData $result,
    ): Payment {
        // Update the host record and return a fresh Payment domain object.
    }
}
```

Regístralo en configuración:

```php
'repositories' => [
    'payments' => App\Payments\Repositories\HostPaymentRepository::class,
    'payment_display' => null,
    'webhooks' => null,
    'payment_methods' => null,
],
```

El repositorio puede usar los nombres de columnas del host. Lo importante es devolver un `Equidna\StagHerd\Domain\Payment` válido.

### 7.3 Usar una API externa

Si Laravel no guarda pagos directamente, el repositorio puede llamar una API interna:

```text
Laravel host
  -> StagHerd
    -> HostPaymentRepository
      -> Payments API
```

El contrato sigue siendo `PaymentRepository`; cambia la implementación interna.

```php
use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Domain\Payment;
use Illuminate\Support\Facades\Http;

final class ApiPaymentRepository implements PaymentRepository
{
    public function storeFromResult(
        PaymentRequestData $request,
        PaymentResultData $result,
    ): Payment {
        $response = Http::post(config('services.payments_api.url') . '/payments', [
            'provider' => $result->provider,
            'method' => $result->method,
            'amount' => $result->amount ?? $request->amount,
            'currency' => $result->currency ?? $request->currency,
            'status' => $result->status->value,
            'provider_status' => $result->providerStatus,
            'provider_payment_id' => $result->references?->providerPaymentId,
            'provider_order_id' => $result->references?->providerOrderId,
            'external_reference' => $request->externalReference,
            'payer_reference' => $request->payerReference,
            'payer_email' => $request->payerEmail,
            'metadata' => $request->metadata,
        ]);

        return $this->mapToPayment($response->json());
    }

    // Implement find, findByProviderPaymentId, findByProviderOrderId,
    // findByExternalReference and updateFromResult.

    private function mapToPayment(array $data): Payment
    {
        // Convert API response to Equidna\StagHerd\Domain\Payment.
    }
}
```

## 8. Montos y referencias

StagHerd trabaja con montos en unidades menores.

```text
100.00 MXN = 10000
100.50 MXN = 10050
25.99 MXN  = 2599
```

Para evitar errores de precisión, procesa y guarda enteros. Convierte a decimal solo para entrada o presentación.

```php
use Equidna\StagHerd\Support\MoneyFormatter;

MoneyFormatter::fromDecimal(100.50);       // 10050
MoneyFormatter::toDecimal(10050);          // 100.5
MoneyFormatter::toDecimalString(10050);    // "100.50"
MoneyFormatter::toMinorUnits(10050);       // 10050
```

Usa `externalReference` para ligar el pago con una entidad estable del host:

```php
externalReference: 'ORDER-1001'
```

Usa `metadata` para información complementaria:

```php
metadata: [
    'order_id' => 1001,
    'client_id' => 25,
    'source' => 'checkout',
]
```

## 9. Crear pagos

Servicio principal:

```php
Equidna\StagHerd\Application\PaymentService
```

Facade:

```php
Equidna\StagHerd\Facades\StagHerd
```

Ejemplo con `PaymentService`:

```php
use Equidna\StagHerd\Application\PaymentService;
use Equidna\StagHerd\Data\PaymentRequestData;

$payment = app(PaymentService::class)->createPayment(
    PaymentRequestData::fromDecimalAmount(
        amount: 100.50,
        currency: 'MXN',
        method: 'cash',
        provider: 'cash',
        externalReference: 'ORDER-1001',
        payerEmail: 'customer@example.com',
        description: 'Payment for order ORDER-1001',
        metadata: [
            'order_id' => 1001,
        ],
    )
);
```

Ejemplo con facade:

```php
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Facades\StagHerd;

$payment = StagHerd::createPayment(
    PaymentRequestData::fromDecimalAmount(
        amount: 100.50,
        currency: 'MXN',
        method: 'cash',
        provider: 'cash',
        externalReference: 'ORDER-1001',
    )
);
```

Recomendación: manda siempre `provider` cuando el host tenga varios proveedores habilitados. Evita que un mismo `method` sea ambiguo.

## 10. Operaciones de pago

### Confirmar

```php
use Equidna\StagHerd\Application\PaymentService;
use Equidna\StagHerd\Data\PaymentConfirmationData;

$payment = app(PaymentService::class)->confirmPayment(
    new PaymentConfirmationData(
        provider: 'cash',
        paymentId: $paymentId,
        reason: 'Manual confirmation',
    )
);
```

Úsalo solo cuando el método requiera una confirmación posterior.

### Consultar

```php
use Equidna\StagHerd\Application\PaymentService;
use Equidna\StagHerd\Data\PaymentLookupData;

$payment = app(PaymentService::class)->lookupPayment(
    new PaymentLookupData(
        provider: 'stripe',
        method: 'card',
        providerPaymentId: $providerPaymentId,
    )
);
```

### Cancelar

```php
use Equidna\StagHerd\Application\PaymentService;
use Equidna\StagHerd\Data\PaymentCancellationData;

$payment = app(PaymentService::class)->cancelPayment(
    new PaymentCancellationData(
        provider: 'cash',
        paymentId: $paymentId,
        reason: 'Customer requested cancellation',
    )
);
```

### Reembolsar

```php
use Equidna\StagHerd\Application\PaymentService;
use Equidna\StagHerd\Data\RefundRequestData;

$payment = app(PaymentService::class)->refundPayment(
    new RefundRequestData(
        provider: 'cash',
        paymentId: $paymentId,
        amount: 10050,
        reason: 'Customer requested refund',
    )
);
```

El monto del reembolso se envía en unidades menores.

### Sincronizar

```php
use Equidna\StagHerd\Application\PaymentService;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;

$payment = app(PaymentService::class)->syncPayment(
    lookup: new PaymentLookupData(
        provider: 'paypal',
        method: 'paypal',
        providerOrderId: $providerOrderId,
    ),
    fallbackRequest: PaymentRequestData::fromDecimalAmount(
        amount: 100.50,
        currency: 'MXN',
        method: 'paypal',
        provider: 'paypal',
        externalReference: 'ORDER-1001',
    ),
);
```

La sincronización sirve cuando el proveedor ya tiene el pago y el host necesita reflejarlo localmente.

## 11. Métodos disponibles para construir checkout

Usa `PaymentMethods` para leer los métodos configurados. No hardcodees métodos en vistas o controladores.

```php
use Equidna\StagHerd\Support\PaymentMethods;

$enabledMethods = PaymentMethods::getMethods(true);
```

Ejemplo:

```php
foreach (PaymentMethods::getMethods(true) as $method => $config) {
    $label = $config['label'] ?? $method;
}
```

El resultado depende de `config/stag-herd.php` y de los proveedores habilitados.

## 12. Tarjetas y métodos tokenizados

StagHerd permite guardar métodos de pago para reutilizarlos después. La API común está en:

```php
Equidna\StagHerd\Application\PaymentMethodService
```

También puedes resolver el contrato:

```php
Equidna\StagHerd\Contracts\ManagesPaymentMethods
```

### Flujo recomendado

1. El frontend obtiene o crea el método/token con el SDK del proveedor.
2. El backend registra el método en StagHerd.
3. El host lista métodos activos por usuario/cliente.
4. El usuario elige uno o usa el método default.
5. El backend crea un pago con `method: 'tokenized_card'` y la referencia del método guardado.

### Registrar un método guardado

```php
use Equidna\StagHerd\Application\PaymentMethodService;
use Equidna\StagHerd\Data\PaymentMethodRegisterData;

$paymentMethod = app(PaymentMethodService::class)->registerPaymentMethod(
    new PaymentMethodRegisterData(
        provider: 'stripe',
        ownerReference: 'USER-25',
        providerCustomerId: 'cus_123',
        providerPaymentMethodId: 'pm_123',
        credentialContext: 'default',
        type: 'card',
        fingerprint: 'card-fingerprint',
        displayName: 'Personal Visa',
        brand: 'visa',
        last4: '4242',
        expMonth: 12,
        expYear: 2030,
        isDefault: true,
    )
);
```

`ownerReference` es la referencia del dueño en el host. Puede ser un usuario, cliente, cuenta u organización.

### Listar métodos del dueño

```php
use Equidna\StagHerd\Application\PaymentMethodService;
use Equidna\StagHerd\Data\PaymentMethodsListData;

$methods = app(PaymentMethodService::class)->listPaymentMethods(
    new PaymentMethodsListData(
        provider: 'stripe',
        ownerReference: 'USER-25',
    )
);
```

### Cambiar método default

```php
use Equidna\StagHerd\Application\PaymentMethodService;
use Equidna\StagHerd\Data\PaymentMethodSetDefaultData;

$default = app(PaymentMethodService::class)->setDefaultPaymentMethod(
    new PaymentMethodSetDefaultData(
        provider: 'stripe',
        ownerReference: 'USER-25',
        providerPaymentMethodId: 'pm_123',
    )
);
```

### Desactivar un método

```php
use Equidna\StagHerd\Application\PaymentMethodService;
use Equidna\StagHerd\Data\PaymentMethodDeactivateData;

$detached = app(PaymentMethodService::class)->deactivatePaymentMethod(
    new PaymentMethodDeactivateData(
        provider: 'stripe',
        ownerReference: 'USER-25',
        providerPaymentMethodId: 'pm_123',
    )
);
```

### Crear pago con tarjeta guardada

```php
use Equidna\StagHerd\Application\PaymentService;
use Equidna\StagHerd\Data\PaymentRequestData;

$payment = app(PaymentService::class)->createPayment(
    PaymentRequestData::fromDecimalAmount(
        amount: 250.00,
        currency: 'MXN',
        method: 'tokenized_card',
        provider: 'stripe',
        externalReference: 'ORDER-1002',
        payerReference: 'USER-25',
        metadata: [
            'provider_payment_method_id' => 'pm_123',
        ],
    )
);
```

Los proveedores con método `tokenized_card` configurado son:

| Proveedor | Método | Uso típico |
| --- | --- | --- |
| `stripe` | `tokenized_card` | Cobrar una tarjeta guardada con PaymentMethod/Customer de Stripe |
| `paypal` | `tokenized_card` | Cobrar un método guardado en PayPal |
| `mercado_pago` | `tokenized_card` | Cobrar una tarjeta guardada/tokenizada de Mercado Pago |

## 13. Checkout hospedado, catálogo y suscripciones

El servicio de billing es:

```php
Equidna\StagHerd\Application\BillingService
```

Facade:

```php
Equidna\StagHerd\Facades\StagHerdBilling
```

### Crear un producto y un precio

```php
use Equidna\StagHerd\Application\BillingService;

$billing = app(BillingService::class);

$product = $billing->createProduct(
    providerName: 'stripe',
    credentialContext: 'default',
    name: 'Pro Plan',
    metadata: ['plan' => 'pro'],
    idempotencyKey: 'product-pro-plan',
);

$price = $billing->createPrice(
    providerName: 'stripe',
    credentialContext: 'default',
    productId: $product->id,
    unitAmount: 29900,
    currency: 'MXN',
    recurringInterval: 'month',
    idempotencyKey: 'price-pro-plan-monthly',
);
```

### Crear checkout de suscripción

```php
use Equidna\StagHerd\Application\BillingService;
use Equidna\StagHerd\Data\BillingLineItemData;
use Equidna\StagHerd\Data\CheckoutRequestData;
use Equidna\StagHerd\Domain\Enums\CheckoutMode;

$checkout = app(BillingService::class)->createCheckout(
    new CheckoutRequestData(
        provider: 'stripe',
        mode: CheckoutMode::SUBSCRIPTION,
        credentialContext: 'default',
        lineItems: [
            new BillingLineItemData(
                priceReference: $price->id,
                quantity: 1,
            ),
        ],
        successUrl: route('billing.success'),
        cancelUrl: route('billing.cancel'),
        customerEmail: 'customer@example.com',
        externalReference: 'ACCOUNT-25',
        metadata: [
            'account_id' => 25,
        ],
        idempotencyKey: 'checkout-account-25-pro',
    )
);

return redirect()->away($checkout->url);
```

### Consultar una suscripción

```php
use Equidna\StagHerd\Application\BillingService;
use Equidna\StagHerd\Data\SubscriptionLookupData;

$subscription = app(BillingService::class)->lookupSubscription(
    new SubscriptionLookupData(
        provider: 'stripe',
        credentialContext: 'default',
        subscriptionId: $providerSubscriptionId,
    )
);
```

### Cancelar una suscripción

```php
use Equidna\StagHerd\Application\BillingService;
use Equidna\StagHerd\Data\SubscriptionCancellationData;

$subscription = app(BillingService::class)->cancelSubscription(
    new SubscriptionCancellationData(
        provider: 'stripe',
        credentialContext: 'default',
        subscriptionId: $providerSubscriptionId,
        atPeriodEnd: true,
        idempotencyKey: 'cancel-subscription-' . $providerSubscriptionId,
    )
);
```

### Portal de cliente

```php
use Equidna\StagHerd\Application\BillingService;
use Equidna\StagHerd\Data\BillingPortalRequestData;

$portal = app(BillingService::class)->createBillingPortal(
    new BillingPortalRequestData(
        provider: 'stripe',
        credentialContext: 'default',
        customerId: $providerCustomerId,
        returnUrl: route('billing.index'),
    )
);

return redirect()->away($portal->url);
```

El soporte de billing depende de cada proveedor. Si un proveedor no soporta una operación específica, el paquete lanzará `UnsupportedOperationException`.

## 14. Webhooks

StagHerd incluye rutas para webhooks:

```text
POST /stag-herd/webhooks/{provider}/{credentialContext}
GET|POST /stag-herd/webhooks/mercado-pago
GET|POST /stag-herd/webhooks/paypal
GET|POST /stag-herd/webhooks/stripe
```

Los parsers normalizan el evento y el paquete usa idempotencia para evitar reprocesar el mismo webhook.

Eventos relacionados:

- `PaymentWebhookReceived`
- `PaymentWebhookProcessed`
- `PaymentWebhookFailed`
- `CheckoutCompleted`
- `InvoicePaid`
- `InvoicePaymentFailed`
- `SubscriptionStatusChanged`

Recomendación: usa los webhooks para mantener sincronizados pagos y suscripciones, pero deja las decisiones de negocio en listeners del host.

## 15. Eventos de pago

Eventos principales:

- `PaymentStateChanged`
- `PaymentPending`
- `PaymentApproved`
- `PaymentRejected`
- `PaymentCanceled`
- `PaymentRefunded`
- `PaymentFailed`

Ejemplo de listener del host:

```php
namespace App\Listeners;

use App\Models\Order;
use Equidna\StagHerd\Events\PaymentApproved;

final class MarkOrderAsPaid
{
    public function handle(PaymentApproved $event): void
    {
        $payment = $event->payment;

        $order = Order::where('reference', $payment->externalReference)->first();

        if (! $order) {
            return;
        }

        $order->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}
```

Registro en el host:

```php
protected $listen = [
    \Equidna\StagHerd\Events\PaymentApproved::class => [
        \App\Listeners\MarkOrderAsPaid::class,
    ],
];
```

StagHerd evita emitir eventos de cambio de estado cuando el estado anterior y el nuevo son iguales. Eso reduce ruido cuando hay consultas, sincronizaciones o webhooks repetidos.

## 16. Estados normalizados

Los pagos usan:

```php
Equidna\StagHerd\Domain\Enums\PaymentStatusEnum
```

Estados:

- `PENDING`
- `APPROVED`
- `REJECTED`
- `CANCELED`
- `REFUNDED`
- `FAILED`

Ejemplo:

```php
use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;

if ($payment->status === PaymentStatusEnum::APPROVED) {
    // Mark the host order as paid.
}
```

El objeto `Payment` también expone helpers:

```php
$payment->isPending();
$payment->isApproved();
$payment->isRejected();
$payment->isCanceled();
$payment->isRefunded();
$payment->isFailed();

$payment->canBeCanceled();
$payment->canBeRefunded();
$payment->isFinal();
```

## 17. Objeto Payment

Las operaciones de pago devuelven:

```php
Equidna\StagHerd\Domain\Payment
```

Campos principales:

- `id`
- `provider`
- `method`
- `amount`
- `currency`
- `status`
- `providerStatus`
- `externalReference`
- `payerReference`
- `payerEmail`
- `references`
- `metadata`

Ejemplo:

```php
$payment->id;
$payment->provider;
$payment->method;
$payment->amount;
$payment->currency;
$payment->status;
$payment->providerStatus;
$payment->externalReference;
$payment->payerEmail;
$payment->metadata;

$payment->toArray();
```

## 18. Excepciones

Todas las excepciones específicas del paquete heredan de:

```php
Equidna\StagHerd\Exceptions\StagHerdException
```

Excepciones relevantes:

- `InvalidPaymentMethodException`
- `PaymentMethodDisabledException`
- `PaymentMethodNotFoundException`
- `ProviderNotConfiguredException`
- `ProviderDisabledException`
- `ProviderNotRegisteredException`
- `PaymentNotFoundException`
- `UnsupportedOperationException`
- `InvalidPaymentPayloadException`
- `InvalidStateTransitionException`
- `PaymentDeclinedException`
- `ProviderCommunicationException`
- `ProviderAuthenticationException`
- `InvalidWebhookSignatureException`
- `DuplicateWebhookException`

Ejemplo de manejo en el host:

```php
use Equidna\StagHerd\Application\PaymentService;
use Equidna\StagHerd\Exceptions\StagHerdException;

try {
    $payment = app(PaymentService::class)->createPayment($request);
} catch (StagHerdException $exception) {
    report($exception);

    return back()->withErrors([
        'payment' => 'No fue posible procesar el pago.',
    ]);
}
```

## 19. Cómo acoplarlo al sistema host

Una integración limpia normalmente queda así:

```text
Checkout / Controller del host
  -> construye PaymentRequestData, CheckoutRequestData o PaymentMethodRegisterData
  -> llama PaymentService, PaymentMethodService o BillingService
  -> guarda/lee mediante repositorios configurados
  -> recibe eventos de StagHerd
  -> actualiza órdenes, facturas, servicios o cuentas del host
```

Responsabilidades recomendadas:

| Capa | Responsabilidad |
| --- | --- |
| Controller del host | Validar request HTTP y llamar servicios de aplicación propios |
| Servicio de aplicación del host | Decidir qué se cobra, a quién, cuánto y con qué método |
| StagHerd | Ejecutar proveedor, normalizar resultado y persistir vía repositorio |
| Repositorio del host | Adaptar tablas/API propias al contrato del paquete |
| Listeners del host | Reaccionar a cambios de estado y aplicar reglas de negocio |

Ejemplo de servicio del host:

```php
namespace App\Orders;

use App\Models\Order;
use Equidna\StagHerd\Application\PaymentService;
use Equidna\StagHerd\Data\PaymentRequestData;

final readonly class PayOrder
{
    public function __construct(
        private PaymentService $payments,
    ) {
    }

    public function handle(Order $order, string $provider, string $method): void
    {
        $payment = $this->payments->createPayment(
            PaymentRequestData::fromDecimalAmount(
                amount: $order->total,
                currency: $order->currency,
                method: $method,
                provider: $provider,
                externalReference: 'ORDER-' . $order->id,
                payerReference: 'USER-' . $order->user_id,
                payerEmail: $order->customer_email,
                description: 'Payment for order ' . $order->id,
                metadata: [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                ],
            )
        );

        $order->update([
            'payment_reference' => $payment->id,
            'payment_status' => $payment->status->value,
        ]);
    }
}
```

Evita poner reglas de órdenes, inventario, facturación o permisos dentro del paquete. Esa lógica pertenece al host.

## 20. Métodos personalizados

El proveedor `custom` permite registrar métodos internos del host:

```php
'custom' => [
    'provider' => CustomProvider::class,
    'enabled' => true,

    'methods' => [
        'client_credit' => [
            'enabled' => true,
            'label' => 'Crédito de cliente',
            'handler' => App\Payments\Handlers\ClientCreditPaymentHandler::class,
        ],
    ],
],
```

Cada handler debe implementar:

```php
Equidna\StagHerd\Contracts\PaymentMethodHandler
```

Métodos requeridos:

```php
public function getMethod(): string;

public function createPayment(PaymentRequestData $request): PaymentResultData;

public function confirmPayment(PaymentConfirmationData $request): PaymentResultData;

public function lookupPayment(PaymentLookupData $request): PaymentResultData;

public function cancelPayment(PaymentCancellationData $request): PaymentResultData;

public function refundPayment(RefundRequestData $request): PaymentResultData;
```

Estos handlers deben validar reglas propias del host, por ejemplo saldo disponible, vigencia de la orden, permisos del cliente o límites internos.

## 21. Checklist de implementación

### Instalación

- [ ] Instalar el paquete con Composer.
- [ ] Publicar `stag-herd-config`.
- [ ] Publicar migraciones si se usarán las tablas del paquete.
- [ ] Ejecutar migraciones.

### Configuración

- [ ] Activar solo los proveedores requeridos.
- [ ] Configurar variables `.env` reales.
- [ ] Definir middleware y prefijos de rutas.
- [ ] Configurar idempotencia de webhooks.

### Persistencia

- [ ] Decidir si se usarán tablas del paquete, tablas del host o API externa.
- [ ] Implementar `PaymentRepository` si el host no usará `stag_herd_payments`.
- [ ] Implementar `PaymentMethodRepository` solo si el host quiere controlar la persistencia de métodos guardados.
- [ ] Validar que los repositorios devuelvan objetos/datos compatibles con el paquete.

### Checkout y pagos

- [ ] Obtener métodos activos con `PaymentMethods::getMethods(true)`.
- [ ] Crear un pago inicial con `cash`.
- [ ] Probar el proveedor externo elegido.
- [ ] Confirmar, consultar, cancelar o reembolsar según el flujo del método.

### Métodos guardados

- [ ] Registrar métodos tokenizados desde el backend.
- [ ] Listar métodos por `ownerReference`.
- [ ] Permitir cambiar default.
- [ ] Permitir desactivar métodos.
- [ ] Crear pagos con `method: 'tokenized_card'`.

### Billing y suscripciones

- [ ] Crear producto y precio si el proveedor lo requiere.
- [ ] Crear checkout hospedado.
- [ ] Guardar `subscriptionId` o recurso equivalente en el host.
- [ ] Consultar y cancelar suscripciones desde `BillingService`.
- [ ] Procesar webhooks de billing.

### Eventos

- [ ] Registrar listeners para pagos aprobados, rechazados, cancelados y reembolsados.
- [ ] Registrar listeners de suscripciones/facturación si aplica.
- [ ] Mantener reglas de negocio en el host, no dentro del paquete.

## 22. Flujo recomendado para una integración nueva

1. Instala el paquete.
2. Publica configuración y migraciones.
3. Ejecuta migraciones.
4. Define si usarás persistencia interna o repositorios del host.
5. Activa `cash` y crea un pago de prueba.
6. Activa el proveedor real: Stripe, PayPal o Mercado Pago.
7. Configura webhooks e idempotencia.
8. Construye el checkout leyendo métodos con `PaymentMethods::getMethods(true)`.
9. Conecta eventos del paquete con órdenes, facturas o servicios del host.
10. Si necesitas reutilizar tarjetas, implementa el flujo de métodos tokenizados.
11. Si necesitas recurrencia, usa `BillingService` para checkout de suscripción, consulta y cancelación.
12. Prueba errores, reintentos, webhooks duplicados y montos en unidades menores.

## 23. Resumen práctico

Para una integración estable:

- Usa `PaymentService` para pagos directos.
- Usa `PaymentMethodService` para tarjetas/métodos guardados.
- Usa `BillingService` para checkout hospedado y suscripciones.
- Usa `externalReference` para enlazar con órdenes, facturas o cuentas del host.
- Guarda montos como enteros en unidades menores.
- Lee métodos activos desde configuración, no desde listas hardcodeadas.
- Maneja cambios de estado con eventos.
- Implementa repositorios propios solo cuando el host necesite controlar su base de datos o una API externa.
- Mantén reglas de negocio en el host.
