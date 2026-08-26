# Matriz de soporte de StagHerd v1.0.0

Este documento define el alcance estable de StagHerd para la versión `v1.0.0`.

El objetivo de esta versión es ofrecer una capa confiable de pagos para Laravel, con soporte para pagos directos, métodos de pago guardados, suscripciones, webhooks y métodos personalizados del sistema host.

## Niveles de soporte

| Nivel | Significado |
| --- | --- |
| Estable | Forma parte de `v1.0.0` y debe estar documentado y probado. |
| Opcional | Disponible para pruebas, demo o flujos internos, pero no forma parte de la promesa principal del paquete. |
| No soportado | No forma parte del alcance de `v1.0.0`. |

## Proveedores de pago

| Proveedor | Estado | Notas |
| --- | --- | --- |
| Stripe | Estable | Soporta pagos con tarjeta, wallets, tarjetas guardadas, SPEI, webhooks, checkout y suscripciones. |
| PayPal | Estable | Soporta checkout normal de PayPal, métodos guardados, webhooks, checkout y suscripciones según lo implementado por el proveedor. |
| Mercado Pago | Estable | Soporta pagos con tarjeta, Checkout Pro, tarjetas guardadas, webhooks, checkout y suscripciones según lo implementado por el proveedor. |
| Custom | Estable | Permite que el sistema host registre métodos de pago internos mediante handlers personalizados. |
| Cash | Opcional | Disponible como método dummy/local para pruebas o flujos manuales internos. No forma parte de la promesa principal del paquete. |

## Métodos de pago directos

| Método | Proveedor | Estado | Propósito |
| --- | --- | --- | --- |
| `card` | Stripe | Estable | Pago con tarjeta mediante Stripe. |
| `apple_pay` | Stripe | Estable | Pago con Apple Pay mediante Stripe. |
| `google_pay` | Stripe | Estable | Pago con Google Pay mediante Stripe. |
| `spei` | Stripe | Estable | Pago SPEI mediante Stripe. |
| `paypal` | PayPal | Estable | Flujo estándar de checkout con PayPal. |
| `card` | Mercado Pago | Estable | Pago con tarjeta mediante Mercado Pago. |
| `checkout_pro` | Mercado Pago | Estable | Flujo hospedado de Mercado Pago Checkout Pro. |
| Métodos personalizados | Custom | Estable | Métodos definidos por el host, como wallet, crédito de cliente, cortesía o saldo interno. |
| `cash` | Cash | Opcional | Método dummy/manual para validación local. |

## Métodos de pago guardados

| Proveedor | Método | Estado | Notas |
| --- | --- | --- | --- |
| Stripe | `tokenized_card` | Estable | Usa un cliente del proveedor y una referencia de método de pago guardado. |
| PayPal | `tokenized_card` | Estable | Usa una referencia de método de pago guardado del proveedor. |
| Mercado Pago | `tokenized_card` | Estable | Usa una tarjeta guardada/tokenizada de Mercado Pago. |

Los métodos guardados se administran mediante:

```php
Equidna\StagHerd\Application\PaymentMethodService
```

o mediante el contrato:

```php
Equidna\StagHerd\Contracts\ManagesPaymentMethods
```

## Billing y suscripciones

| Capacidad | Estado | Punto de entrada |
| --- | --- | --- |
| Checkout hospedado | Estable | `BillingService::createCheckout()` |
| Consulta de checkout | Estable | `BillingService::lookupCheckout()` |
| Consulta de suscripción | Estable | `BillingService::lookupSubscription()` |
| Cancelación de suscripción | Estable | `BillingService::cancelSubscription()` |
| Creación de producto | Estable | `BillingService::createProduct()` |
| Creación de precio | Estable | `BillingService::createPrice()` |
| Portal de cliente | Estable cuando el proveedor lo soporte | `BillingService::createBillingPortal()` |

Las operaciones de billing dependen del soporte real de cada proveedor. Si un proveedor no soporta una operación, StagHerd debe fallar de forma controlada mediante `UnsupportedOperationException`.

## Webhooks

| Proveedor | Estado | Notas |
| --- | --- | --- |
| Stripe | Estable | El parsing de webhooks y los eventos normalizados forman parte de `v1.0.0`. |
| PayPal | Estable | El parsing de webhooks y los eventos normalizados forman parte de `v1.0.0`. |
| Mercado Pago | Estable | El parsing de webhooks y los eventos normalizados forman parte de `v1.0.0`. |

La idempotencia de webhooks debe configurarse con:

```env
STAG_HERD_WEBHOOK_IDEMPOTENCY_DRIVER=database
STAG_HERD_WEBHOOK_IDEMPOTENCY_TTL=86400
```

Drivers esperados:

```text
database
redis
```

## Persistencia

| Área | Persistencia por defecto | Personalizable |
| --- | --- | --- |
| Pagos | `stag_herd_payments` | Sí, mediante `PaymentRepository`. |
| Métodos de pago | `stag_herd_payment_methods` | Sí, mediante `PaymentMethodRepository`. |
| Recursos de billing | `stag_herd_billing_resources` | Sí, mediante `BillingResourceRepository`. |
| Eventos de webhook | `stag_herd_webhook_events` | Sí, mediante la configuración de idempotencia de webhooks. |

El sistema host puede usar las tablas de StagHerd, sus propias tablas o APIs externas implementando los contratos correspondientes.

## Servicios públicos

| Servicio | Estado | Responsabilidad |
| --- | --- | --- |
| `PaymentService` | Estable | Operaciones de pagos directos. |
| `PaymentMethodService` | Estable | Métodos de pago guardados/tokenizados. |
| `BillingService` | Estable | Checkout hospedado, suscripciones, productos, precios y portal de cliente. |

## Facades públicas

| Facade | Estado | Servicio |
| --- | --- | --- |
| `StagHerd` | Estable | `PaymentService` |
| `StagHerdBilling` | Estable | `BillingService` |

## Eventos

| Grupo de eventos | Estado | Propósito |
| --- | --- | --- |
| Eventos de pago | Estable | Permiten que el host reaccione a cambios de estado de pagos. |
| Eventos de webhook | Estable | Permiten que el host observe el procesamiento de webhooks. |
| Eventos de billing | Estable | Permiten que el host reaccione a checkout, facturas y suscripciones. |

## Demo y UI

El paquete puede incluir vistas demo o assets frontend, pero son opcionales.

Para `v1.0.0`, StagHerd se considera primero un paquete backend de pagos para Laravel. La UI no es obligatoria para la promesa estable del paquete, a menos que se documente explícitamente como una parte soportada del producto.

## Regla de release para v1.0.0

Una funcionalidad se considera parte de `v1.0.0` solo si tiene:

- Documentación.
- Tests.
- API pública estable o contrato documentado.
- Configuración clara.
- Errores controlados cuando algo no está soportado.