# Stag Herd

`stag-herd` es un paquete Laravel para manejar pagos de forma desacoplada del dominio del sistema host.

El objetivo del paquete es ofrecer un core de pagos reutilizable, con providers configurables, respuestas normalizadas, persistencia interna por defecto y posibilidad de reemplazar repositorios cuando el host necesite usar sus propias tablas.

## Estado actual

El paquete se encuentra en evolución hacia una arquitectura más desacoplada.

La fase actual contempla:

- provider interno `cash`;
- provider externo `mercado_pago`;
- creación de pagos;
- consulta de pagos;
- cancelación;
- reembolso;
- persistencia interna por defecto;
- repositorios reemplazables por configuración;
- procesamiento de webhooks para Mercado Pago y PayPal (mínimo).

Los webhooks reutilizan el flujo parser -> idempotencia -> lookup -> update. En PayPal, por ahora solo se procesa `PAYMENT.CAPTURE.COMPLETED` como evento terminal útil.

## Instalación

```bash
composer require equidna/stag-herd
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

## Configuración básica

El archivo principal de configuración es:

```php
config/stag-herd.php
```

Por defecto, el paquete usa sus propias tablas internas.

```php
'repositories' => [
    'payments' => null,
    'payment_display' => null,
    'webhooks' => null,
],
```

Si `payments` es `null`, el paquete usa:

```php
Equidna\StagHerd\Infrastructure\Persistence\EloquentPaymentRepository::class
```

## Tabla interna de pagos

La persistencia interna utiliza la tabla:

```txt
stag_herd_payments
```

Columnas principales:

```txt
id
provider
method
amount
currency
status
provider_status
payer_reference
payer_email
provider_payment_id
provider_order_id
metadata
raw_payload
created_at
updated_at
```

El paquete guarda como columnas principales únicamente:

```txt
provider_payment_id
provider_order_id
```

Otras referencias del provider, como `external_reference`, `provider_transaction_id` o `provider_refund_id`, se conservan en `metadata` o `raw_payload`.

## Providers

Los providers se configuran en:

```php
'providers' => [
    'cash' => [
        'provider' => Equidna\StagHerd\Infrastructure\Providers\Cash\CashProvider::class,
        'enabled' => true,
    ],

    'mercado_pago' => [
        'provider' => Equidna\StagHerd\Infrastructure\Providers\MercadoPago\MercadoPagoProvider::class,
        'enabled' => false,
    ],
],
```

Para activar Mercado Pago:

```php
'mercado_pago' => [
    'enabled' => true,

    'credentials' => [
        'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN'),
        'public_key' => env('MERCADO_PAGO_PUBLIC_KEY'),
        'webhook_secret' => env('MERCADO_PAGO_WEBHOOK_SECRET'),
    ],
],
```

Variables de entorno:

```env
MERCADO_PAGO_ACCESS_TOKEN=
MERCADO_PAGO_PUBLIC_KEY=
MERCADO_PAGO_WEBHOOK_SECRET=
```

## Crear un pago

```php
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Facades\StagHerd;

$payment = StagHerd::createPayment(
    new PaymentRequestData(
        amount: 12000,
        currency: 'MXN',
        method: 'cash',
        provider: 'cash',
        externalReference: 'ORDER-123',
        payerEmail: 'cliente@example.com',
        metadata: [
            'source' => 'checkout',
        ],
    )
);
```

El monto se maneja como entero en centavos.

Ejemplo:

```txt
12000 = $120.00 MXN
```

## Crear un pago con Mercado Pago

```php
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Facades\StagHerd;

$payment = StagHerd::createPayment(
    new PaymentRequestData(
        amount: 12000,
        currency: 'MXN',
        method: 'card',
        provider: 'mercado_pago',
        externalReference: 'ORDER-123',
        payerEmail: 'cliente@example.com',
        metadata: [
            'mercado_pago' => [
                'token' => $cardToken,
                'payment_method_id' => 'visa',
                'installments' => 1,
            ],
        ],
    )
);
```

## Consultar un pago

Buscar por ID local:

```php
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Facades\StagHerd;

$payment = StagHerd::lookupPayment(
    new PaymentLookupData(
        provider: 'mercado_pago',
        paymentId: '1',
    )
);
```

Buscar por ID de pago del provider:

```php
$payment = StagHerd::lookupPayment(
    new PaymentLookupData(
        provider: 'mercado_pago',
        providerPaymentId: '123456789',
    )
);
```

Buscar por ID de orden del provider:

```php
$payment = StagHerd::lookupPayment(
    new PaymentLookupData(
        provider: 'mercado_pago',
        providerOrderId: 'ORDER-ID',
    )
);
```

## Cancelar un pago

```php
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Facades\StagHerd;

$payment = StagHerd::cancelPayment(
    new PaymentCancellationData(
        provider: 'mercado_pago',
        providerPaymentId: '123456789',
        reason: 'Cancelado por el usuario',
    )
);
```

## Reembolsar un pago

```php
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Facades\StagHerd;

$payment = StagHerd::refundPayment(
    new RefundRequestData(
        provider: 'mercado_pago',
        providerPaymentId: '123456789',
        amount: 12000,
        reason: 'Reembolso solicitado por el cliente',
    )
);
```

## Integración recomendada para el host

La forma recomendada de integración es:

1. El paquete guarda el pago en `stag_herd_payments`.
2. El host guarda su propia orden, cliente o servicio en sus tablas.
3. El host relaciona ambos mediante `externalReference`, `payerReference`, `provider_payment_id` o `provider_order_id`.
4. El host escucha eventos del paquete para actualizar su propio dominio.

De esta forma, el host no necesita implementar un repositorio custom desde el inicio.

## Repositorios custom

Si el host necesita usar sus propias tablas de pagos, puede implementar:

```php
Equidna\StagHerd\Contracts\PaymentRepository
```

Y registrarlo en configuración:

```php
'repositories' => [
    'payments' => App\Payments\Repositories\HostPaymentRepository::class,
],
```

Si no se configura un repositorio custom, el paquete usa su repositorio interno.

## Demo UI

La demo está apagada por defecto.

```php
'demo' => [
    'enabled' => false,
],
```

Para activarla en un proyecto de pruebas:

```php
'demo' => [
    'enabled' => true,
    'middleware' => ['web'],
    'prefix' => 'stag-herd/payments',
],
```

Rutas de demo:

```txt
/stag-herd/payments
```

La demo no debe considerarse parte obligatoria del core del paquete.

## Webhooks

Los webhooks se configuran en:

```php
'webhooks' => [
    'routes' => [
        'enabled' => true,
        'prefix' => 'stag-herd/webhooks',
        'middleware' => ['api'],
    ],
],
```

Endpoints actuales:

```txt
/stag-herd/webhooks/mercado-pago
/stag-herd/webhooks/paypal
```

PayPal requiere configurar `PAYPAL_WEBHOOK_ID` para que el paquete verifique la firma vía la API oficial de PayPal antes de normalizar el evento.

En la fase actual de PayPal solo se soporta `PAYMENT.CAPTURE.COMPLETED`; cualquier otro evento responde con error controlado.

## Arquitectura general

Estructura principal:

```txt
src/
  Application/
    Actions/
    PaymentService.php

  Contracts/
    PaymentProvider.php
    PaymentRepository.php

  Data/
    PaymentRequestData.php
    PaymentResultData.php
    ProviderReferencesData.php

  Domain/
    Payment.php
    PaymentStateMachine.php
    Enums/

  Infrastructure/
    Persistence/
    Providers/

  Events/
  Exceptions/
  Http/
  Support/
```

## Principios del paquete

El paquete busca mantener:

- bajo acoplamiento con el host;
- providers intercambiables;
- respuestas normalizadas;
- persistencia interna por defecto;
- repositorios custom opcionales;
- separación entre aplicación, dominio e infraestructura;
- configuración clara desde `config/stag-herd.php`;
- secretos únicamente en `.env`.
