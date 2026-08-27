# Stag Herd

`stag-herd` es un paquete Laravel para integrar pagos de forma desacoplada del dominio del sistema host.

El paquete permite trabajar con pagos directos, métodos de pago guardados, suscripciones, webhooks, eventos de dominio y persistencia configurable, sin acoplar la lógica principal del sistema a un proveedor específico.

El host decide qué se cobra, a quién se cobra y qué reglas de negocio aplicar. StagHerd se encarga de ejecutar el proveedor configurado, normalizar el resultado y devolver objetos consistentes.

## Estado y alcance actual

Este README describe la superficie pública preparada para una liberación `1.0.0`.
No promete paridad entre proveedores: cada flujo depende del proveedor, método y
configuración habilitados.

Consulta `docs/support-matrix.md` para el inventario de implementación y sus
límites, y `docs/release-closure-checklist.md` antes de etiquetar o publicar.
Los flujos internos de onboarding/partner referral de PayPal no forman parte de
la superficie soportada de esta liberación.

El código contiene rutas para:

- pagos directos;
- métodos de pago guardados/tokenizados;
- checkout hospedado;
- suscripciones;
- webhooks;
- eventos de dominio;
- persistencia configurable;
- métodos personalizados del sistema host.

StagHerd registra adaptadores para Stripe, PayPal y Mercado Pago, además de
métodos personalizados definidos por el host. Esto no implica que todos los
métodos u operaciones de billing tengan el mismo soporte entre proveedores.

El provider `cash` existe como método dummy/local para pruebas o flujos manuales internos, pero no forma parte de la promesa principal del paquete.

## Instalación

Instala la versión pública con Composer:

```bash
composer require equidna/stag-herd:^1.0 -W
```

Para desarrollo local desde este repositorio, puedes registrar una dependencia
`path` en el `composer.json` de la aplicación host:

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

Publicar configuración:

```bash
php artisan vendor:publish --tag=stag-herd-config
```

Publicar migraciones:

```bash
php artisan vendor:publish --tag=stag-herd-migrations
```

Ejecutar migraciones:

```bash
php artisan migrate
```

Opcionalmente, publicar assets del paquete:

```bash
php artisan vendor:publish --tag=stag-herd-assets
```

## Configuración principal

El archivo principal de configuración es:

```text
config/stag-herd.php
```

Las secciones principales son:

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
        'enabled' => false,
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
```

Cuando un repositorio está en `null`, StagHerd usa su implementación interna.

Las rutas públicas de métodos guardados están deshabilitadas por defecto. Si el
host las habilita, debe agregar middleware de autenticación/autorización que
derive o valide `owner_reference` contra el principal autenticado; `api` por sí
solo no hace esa validación.

## Proveedores configurables

### Stripe

Variables de entorno:

```env
STRIPE_SECRET_KEY=
VITE_STRIPE_PUBLIC_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_API_VERSION=2026-02-25.clover
STRIPE_BASE_URI=https://api.stripe.com
```

Métodos disponibles:

```text
card
apple_pay
google_pay
tokenized_card
spei
```

### PayPal

Variables de entorno:

```env
VITE_PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=
PAYPAL_SECRET=
PAYPAL_WEBHOOK_ID=
PAYPAL_ENVIRONMENT=sandbox
PAYPAL_BASE_URI=https://api-m.sandbox.paypal.com
```

`PAYPAL_SECRET` funciona como fallback de compatibilidad.

La ruta interna `POST /stag-herd/payments/paypal/onboarding/referral` queda
deshabilitada por defecto mediante
`stag-herd.providers.paypal.routes.onboarding_referral.enabled`. No forma parte
de la superficie soportada de esta liberación.

Métodos disponibles:

```text
paypal
tokenized_card
```

### Mercado Pago

Variables de entorno:

```env
MERCADO_PAGO_ACCESS_TOKEN=
VITE_MERCADO_PAGO_PUBLIC_KEY=
MERCADO_PAGO_WEBHOOK_SECRET=
MERCADO_PAGO_BASE_URI=https://api.mercadopago.com
```

Métodos disponibles:

```text
card
checkout_pro
tokenized_card
```

### Custom

El provider `custom` permite registrar métodos propios del sistema host, por ejemplo:

```text
wallet
client_credit
courtesy
internal_balance
manual_transfer
```

Cada método personalizado debe tener un handler que implemente:

```php
Equidna\StagHerd\Contracts\PaymentMethodHandler
```

### Cash

El provider `cash` está disponible como método dummy/local.

Puede usarse para pruebas, validaciones internas o flujos manuales simples.

## Persistencia

StagHerd permite usar las tablas del paquete o reemplazar la persistencia con repositorios propios del host.

Tablas principales incluidas:

| Tabla | Uso |
| --- | --- |
| `stag_herd_payments` | Pagos normalizados. |
| `stag_herd_payment_methods` | Métodos de pago guardados. |
| `stag_herd_provider_customers` | Relación auxiliar con clientes del proveedor. |
| `stag_herd_billing_resources` | Recursos de billing como checkout sessions y suscripciones. |
| `stag_herd_webhook_events` | Eventos de webhook e idempotencia. |

## Repositorios configurables

### Pagos

Contrato:

```php
Equidna\StagHerd\Contracts\PaymentRepository
```

Configuración:

```php
'repositories' => [
    'payments' => App\Payments\Repositories\HostPaymentRepository::class,
],
```

Si `payments` es `null`, StagHerd usa su repositorio Eloquent interno.

### Métodos de pago guardados

Contrato:

```php
Equidna\StagHerd\Contracts\PaymentMethodRepository
```

Configuración:

```php
'repositories' => [
    'payment_methods' => App\Payments\Repositories\HostPaymentMethodRepository::class,
],
```

Si `payment_methods` es `null`, StagHerd usa su repositorio Eloquent interno.

### Billing

Contrato:

```php
Equidna\StagHerd\Contracts\BillingResourceRepository
```

StagHerd usa este repositorio para guardar recursos como checkout sessions y suscripciones.

### Webhooks

La idempotencia de webhooks se configura con:

```env
STAG_HERD_WEBHOOK_IDEMPOTENCY_DRIVER=database
STAG_HERD_WEBHOOK_IDEMPOTENCY_TTL=86400
```

Drivers esperados:

```text
database
redis
```

## Servicios principales

| Servicio | Responsabilidad |
| --- | --- |
| `PaymentService` | Crear, consultar, confirmar, cancelar, reembolsar y sincronizar pagos. |
| `PaymentMethodService` | Registrar, listar, marcar como default y desactivar métodos guardados. |
| `BillingService` | Crear checkout hospedado, manejar suscripciones, productos, precios y portal de cliente. |

Facades disponibles:

| Facade | Servicio |
| --- | --- |
| `StagHerd` | `PaymentService` |
| `StagHerdBilling` | `BillingService` |

## Crear un pago

```php
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Facades\StagHerd;

$payment = StagHerd::createPayment(
    PaymentRequestData::fromDecimalAmount(
        amount: 100.50,
        currency: 'MXN',
        method: 'card',
        provider: 'stripe',
        externalReference: 'ORDER-1001',
        payerReference: 'USER-25',
        payerEmail: 'cliente@example.com',
        description: 'Pago de la orden ORDER-1001',
        metadata: [
            'order_id' => 1001,
            'user_id' => 25,
        ],
    )
);
```

Recomendación: enviar siempre `provider` para evitar ambigüedad cuando existan varios proveedores habilitados.

## Operaciones de pago

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

### Confirmar

```php
use Equidna\StagHerd\Application\PaymentService;
use Equidna\StagHerd\Data\PaymentConfirmationData;

$payment = app(PaymentService::class)->confirmPayment(
    new PaymentConfirmationData(
        provider: 'stripe',
        paymentId: $paymentId,
        reason: 'Confirmación del pago',
    )
);
```

### Cancelar

```php
use Equidna\StagHerd\Application\PaymentService;
use Equidna\StagHerd\Data\PaymentCancellationData;

$payment = app(PaymentService::class)->cancelPayment(
    new PaymentCancellationData(
        provider: 'stripe',
        paymentId: $paymentId,
        reason: 'Cancelación solicitada por el cliente',
    )
);
```

### Reembolsar

```php
use Equidna\StagHerd\Application\PaymentService;
use Equidna\StagHerd\Data\RefundRequestData;

$payment = app(PaymentService::class)->refundPayment(
    new RefundRequestData(
        provider: 'stripe',
        paymentId: $paymentId,
        amount: 10050,
        reason: 'Reembolso solicitado por el cliente',
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

## Montos

StagHerd trabaja internamente con montos en unidades menores.

Ejemplos:

```text
100.00 MXN = 10000
100.50 MXN = 10050
25.99 MXN  = 2599
```

Para crear requests con decimal:

```php
use Equidna\StagHerd\Data\PaymentRequestData;

$request = PaymentRequestData::fromDecimalAmount(
    amount: 100.50,
    currency: 'MXN',
    method: 'card',
    provider: 'stripe',
);
```

Para convertir montos:

```php
use Equidna\StagHerd\Support\MoneyFormatter;

MoneyFormatter::fromDecimal(100.50);       // 10050
MoneyFormatter::toDecimal(10050);          // 100.5
MoneyFormatter::toDecimalString(10050);    // "100.50"
MoneyFormatter::toMinorUnits(10050);       // 10050
```

Recomendación: guardar y operar montos como enteros. Convertir a decimal solo para entrada o presentación.

## Métodos disponibles para checkout

No hardcodees métodos de pago en vistas o controladores.

Usa:

```php
use Equidna\StagHerd\Support\PaymentMethods;

$methods = PaymentMethods::getMethods(true);
```

Ejemplo:

```php
foreach (PaymentMethods::getMethods(true) as $method => $config) {
    $label = $config['label'] ?? $method;
}
```

El resultado depende de los proveedores y métodos habilitados en `config/stag-herd.php`.

## Métodos de pago guardados

Servicio:

```php
Equidna\StagHerd\Application\PaymentMethodService
```

Contrato:

```php
Equidna\StagHerd\Contracts\ManagesPaymentMethods
```

Flujo recomendado:

1. El frontend obtiene el token o método de pago con el SDK del proveedor.
2. El backend registra el método en StagHerd.
3. El host lista métodos guardados por usuario, cliente, cuenta u organización.
4. El usuario elige un método o usa el default.
5. El backend crea el pago con `method: 'tokenized_card'`.

### Registrar método guardado

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

### Listar métodos guardados

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

### Marcar método default

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

### Desactivar método guardado

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

### Crear pago con método guardado

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

## Billing y suscripciones

Servicio:

```php
Equidna\StagHerd\Application\BillingService
```

Facade:

```php
Equidna\StagHerd\Facades\StagHerdBilling
```

### Crear producto

```php
use Equidna\StagHerd\Application\BillingService;

$product = app(BillingService::class)->createProduct(
    providerName: 'stripe',
    credentialContext: 'default',
    name: 'Plan Pro',
    metadata: [
        'plan' => 'pro',
    ],
    idempotencyKey: 'product-pro-plan',
);
```

### Crear precio

```php
use Equidna\StagHerd\Application\BillingService;

$price = app(BillingService::class)->createPrice(
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
        customerEmail: 'cliente@example.com',
        externalReference: 'ACCOUNT-25',
        metadata: [
            'account_id' => 25,
        ],
        idempotencyKey: 'checkout-account-25-pro',
    )
);

return redirect()->away($checkout->url);
```

### Consultar suscripción

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

### Cancelar suscripción

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

Las operaciones de billing no son portables: Stripe implementa checkout de pago y
suscripción, catálogo y portal; PayPal y Mercado Pago solo implementan checkout
de suscripción con una línea; Mercado Pago no crea productos; y PayPal/Mercado
Pago no admiten cancelar al final del período. Las operaciones no implementadas
lanzan `UnsupportedOperationException`.

## Webhooks

Rutas incluidas:

```text
POST /stag-herd/webhooks/{provider}/{credentialContext}
GET|POST /stag-herd/webhooks/mercado-pago
GET|POST /stag-herd/webhooks/paypal
GET|POST /stag-herd/webhooks/stripe
```

Eventos relacionados:

- `PaymentWebhookReceived`
- `PaymentWebhookProcessed`
- `PaymentWebhookFailed`
- `CheckoutCompleted`
- `InvoicePaid`
- `InvoicePaymentFailed`
- `SubscriptionStatusChanged`

Los webhooks usan idempotencia para evitar reprocesar el mismo evento.

## Eventos de pago

Eventos principales:

- `PaymentStateChanged`
- `PaymentPending`
- `PaymentApproved`
- `PaymentRejected`
- `PaymentCanceled`
- `PaymentRefunded`
- `PaymentFailed`

Ejemplo de listener en el host:

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

Registro del listener:

```php
protected $listen = [
    \Equidna\StagHerd\Events\PaymentApproved::class => [
        \App\Listeners\MarkOrderAsPaid::class,
    ],
];
```

## Estados de pago

Enum:

```php
Equidna\StagHerd\Domain\Enums\PaymentStatusEnum
```

Estados disponibles:

```text
PENDING
APPROVED
REJECTED
CANCELED
REFUNDED
FAILED
```

Ejemplo:

```php
use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;

if ($payment->status === PaymentStatusEnum::APPROVED) {
    // Marcar la orden como pagada.
}
```

Helpers disponibles en el objeto `Payment`:

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

## Objeto Payment

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
$payment->metadata;

$payment->toArray();
```

## Excepciones

Todas las excepciones principales heredan de:

```php
Equidna\StagHerd\Exceptions\StagHerdException
```

Ejemplo:

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

Excepciones comunes:

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

## Integración recomendada para el host

Una integración limpia normalmente queda así:

```text
Controller del host
  -> Servicio de aplicación del host
    -> PaymentService / PaymentMethodService / BillingService
      -> Repositorios configurados
        -> Base de datos del paquete, base del host o API externa
```

Responsabilidades recomendadas:

| Capa | Responsabilidad |
| --- | --- |
| Controller | Validar request HTTP. |
| Servicio del host | Decidir qué se cobra, cuánto, a quién y con qué método. |
| StagHerd | Ejecutar proveedor, normalizar resultado y emitir eventos. |
| Repositorio | Adaptar la persistencia del host al contrato del paquete. |
| Listener | Actualizar órdenes, facturas, servicios o cuentas del host. |

Ejemplo:

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

La lógica de órdenes, inventario, facturación, permisos o activación de servicios debe vivir en el host, no dentro del paquete.

## Métodos personalizados

El provider `custom` permite registrar métodos internos del host.

Ejemplo:

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

El handler debe implementar:

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

El handler personalizado debe validar reglas propias del host, como saldo disponible, permisos del cliente, vigencia de la orden o límites internos.

## Demo y UI

El paquete puede incluir vistas demo o assets frontend, pero son opcionales.

El paquete incluye assets opcionales, pero esta documentación no los presenta
como una superficie de release. Valida el alcance de cualquier liberación con
`docs/release-closure-checklist.md`.

## Checklist de implementación

- [ ] Instalar el paquete.
- [ ] Publicar configuración.
- [ ] Publicar migraciones si se usarán tablas del paquete.
- [ ] Ejecutar migraciones.
- [ ] Activar proveedores necesarios.
- [ ] Configurar variables `.env`.
- [ ] Definir persistencia interna o repositorios propios.
- [ ] Crear un pago inicial de prueba.
- [ ] Obtener métodos activos con `PaymentMethods::getMethods(true)`.
- [ ] Configurar webhooks.
- [ ] Registrar listeners del host.
- [ ] Implementar métodos guardados si el sistema reutiliza tarjetas.
- [ ] Implementar billing/suscripciones si el sistema cobra de forma recurrente.
- [ ] Probar errores, webhooks duplicados y montos en unidades menores.

## Cierre de release

Antes de publicar, registra los resultados y la evidencia exigida por
`docs/release-closure-checklist.md`. Los comandos definidos por el repositorio
son:

```bash
composer validate --strict
composer test
composer phpstan
vendor/bin/php-cs-fixer fix --dry-run --diff --verbose
composer audit
```

La instalación en una aplicación Laravel limpia debe validarse contra la versión
que se vaya a liberar; el resultado forma parte de la evidencia de cierre:

```bash
composer require equidna/stag-herd:<release-version>
php artisan vendor:publish --tag=stag-herd-config
php artisan vendor:publish --tag=stag-herd-migrations
php artisan migrate
php artisan route:list
```

## Más documentación

- `docs/implementation.md`: guía completa de implementación.
- `docs/support-matrix.md`: implementación actual y límites por proveedor.
- `docs/environment.md`: variables de entorno.
- `docs/sandbox-test-matrix.md`: matriz de pruebas sandbox.
- `docs/release-closure-checklist.md`: evidencia necesaria antes de liberar.
