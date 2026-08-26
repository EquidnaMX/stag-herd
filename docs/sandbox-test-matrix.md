# Matriz de pruebas sandbox

Esta matriz define las pruebas mínimas que deben ejecutarse antes de liberar StagHerd como `v1.0.0`.

El objetivo no es probar cada caso posible, sino comprobar que los flujos prometidos funcionan en una aplicación host real con credenciales sandbox/test.

## Prerrequisitos

| Requisito | Criterio |
| --- | --- |
| Aplicación host | StagHerd instalado en una app Laravel limpia o equivalente. |
| Configuración publicada | `config/stag-herd.php` publicado y revisado. |
| Migraciones | Migraciones publicadas y ejecutadas si se usan tablas del paquete. |
| Variables `.env` | Configuradas solo para los proveedores bajo prueba. |
| URLs públicas | Webhooks y return URLs accesibles si el proveedor lo requiere. |
| Webhooks | Registrados en el dashboard sandbox/test del proveedor. |
| Idempotencia | `STAG_HERD_WEBHOOK_IDEMPOTENCY_DRIVER` configurado. |
| Logs | Errores visibles sin exponer secretos ni payloads sensibles completos. |

## Evidencia requerida

Por cada prueba manual o sandbox guarda:

| Evidencia | Ejemplo |
| --- | --- |
| ID local | ID del pago o recurso creado por StagHerd. |
| ID del proveedor | Payment Intent, order ID, subscription ID, checkout ID, etc. |
| Estado final | `APPROVED`, `PENDING`, `FAILED`, `CANCELED`, etc. |
| Evento webhook | Event ID, request ID o hash seguro del evento. |
| Resultado esperado | Qué debía ocurrir en el host. |
| Resultado real | Qué ocurrió realmente. |

## Instalación limpia

| Caso | Pasos | Resultado esperado |
| --- | --- | --- |
| Instalar paquete | `composer require equidna/stag-herd` | El paquete instala sin conflictos. |
| Publicar configuración | `php artisan vendor:publish --tag=stag-herd-config` | Se crea `config/stag-herd.php`. |
| Publicar migraciones | `php artisan vendor:publish --tag=stag-herd-migrations` | Se publican las migraciones del paquete. |
| Ejecutar migraciones | `php artisan migrate` | Se crean las tablas necesarias. |
| Revisar rutas | `php artisan route:list` | Aparecen rutas de pagos, métodos guardados y webhooks cuando están habilitadas. |
| Resolver servicios | Resolver `PaymentService`, `PaymentMethodService` y `BillingService` desde el contenedor | Los servicios se resuelven sin errores. |

## Stripe

| Caso | Pasos | Resultado esperado |
| --- | --- | --- |
| Pago con tarjeta | Crear pago con `provider: 'stripe'` y `method: 'card'` | El pago se crea y el estado se normaliza correctamente. |
| Apple Pay | Crear pago con `method: 'apple_pay'` usando credenciales test | El flujo responde sin error de configuración. |
| Google Pay | Crear pago con `method: 'google_pay'` usando credenciales test | El flujo responde sin error de configuración. |
| SPEI | Crear pago con `method: 'spei'` | Se crea la intención/referencia SPEI esperada. |
| Tarjeta guardada | Registrar método y cobrar con `method: 'tokenized_card'` | El pago usa el método guardado correcto. |
| Checkout hospedado | Crear checkout con `BillingService::createCheckout()` | Se obtiene una URL o sesión válida del proveedor. |
| Suscripción | Crear checkout de suscripción y completar flujo sandbox | Se obtiene `subscriptionId` o recurso equivalente. |
| Consulta de suscripción | Consultar con `lookupSubscription()` | Se devuelve estado normalizado. |
| Cancelación de suscripción | Cancelar con `cancelSubscription()` | La suscripción queda cancelada o marcada para cancelar al final del periodo. |
| Portal de cliente | Crear portal con `createBillingPortal()` | Se obtiene URL válida si el proveedor lo soporta. |
| Webhook firmado | Enviar evento firmado con `STRIPE_WEBHOOK_SECRET` | Responde correctamente y no procesa firmas inválidas. |
| Webhook duplicado | Reenviar el mismo evento | No duplica efectos en el host. |

## PayPal

| Caso | Pasos | Resultado esperado |
| --- | --- | --- |
| Checkout normal | Crear pago con `provider: 'paypal'` y `method: 'paypal'` | Se crea orden o acción siguiente válida. |
| Captura/confirmación | Completar el flujo sandbox del comprador | El pago se normaliza a estado final correcto. |
| Tarjeta guardada | Registrar método y cobrar con `method: 'tokenized_card'` | El pago usa el método guardado correcto. |
| Checkout hospedado | Crear checkout con `BillingService::createCheckout()` si el provider lo soporta | Se obtiene sesión o error controlado si no está soportado. |
| Suscripción | Probar flujo de suscripción si está soportado por el provider | Se obtiene suscripción normalizada o error controlado. |
| Consulta de suscripción | Consultar con `lookupSubscription()` | Se devuelve estado normalizado o error controlado. |
| Cancelación de suscripción | Cancelar con `cancelSubscription()` | Se cancela o falla de forma controlada. |
| Webhook firmado | Recibir webhook real de PayPal con `PAYPAL_WEBHOOK_ID` configurado | La firma se valida antes de procesar el evento. |
| Webhook duplicado | Reenviar el mismo evento | No duplica efectos en el host. |

## Mercado Pago

| Caso | Pasos | Resultado esperado |
| --- | --- | --- |
| Pago con tarjeta | Crear pago con `provider: 'mercado_pago'` y `method: 'card'` | El pago se crea y el estado se normaliza correctamente. |
| Checkout Pro | Crear pago con `method: 'checkout_pro'` | Se obtiene flujo/URL de checkout válida. |
| Tarjeta guardada | Registrar método y cobrar con `method: 'tokenized_card'` | El pago usa la tarjeta guardada correcta. |
| Checkout hospedado | Crear checkout con `BillingService::createCheckout()` si el provider lo soporta | Se obtiene sesión o error controlado si no está soportado. |
| Suscripción | Probar flujo de suscripción si está soportado por el provider | Se obtiene suscripción normalizada o error controlado. |
| Consulta de suscripción | Consultar con `lookupSubscription()` | Se devuelve estado normalizado o error controlado. |
| Cancelación de suscripción | Cancelar con `cancelSubscription()` | Se cancela o falla de forma controlada. |
| Webhook firmado | Recibir webhook con `MERCADO_PAGO_WEBHOOK_SECRET` configurado | La firma se valida antes de procesar el evento. |
| Webhook duplicado | Reenviar el mismo evento | No duplica efectos en el host. |

## Custom methods

| Caso | Pasos | Resultado esperado |
| --- | --- | --- |
| Handler registrado | Registrar un método custom en `config/stag-herd.php` | El método aparece como habilitado. |
| Crear pago custom | Crear pago usando el método custom | El handler se ejecuta y devuelve `PaymentResultData`. |
| Confirmar pago custom | Confirmar si el handler lo soporta | El estado se normaliza correctamente. |
| Cancelar pago custom | Cancelar si el handler lo soporta | El pago cambia de estado o falla controladamente. |
| Reembolsar pago custom | Reembolsar si el handler lo soporta | El pago cambia de estado o falla controladamente. |
| Reglas del host | Probar saldo, permisos o reglas internas | El handler impide operaciones inválidas. |

## Métodos de pago guardados

| Caso | Pasos | Resultado esperado |
| --- | --- | --- |
| Registrar método | Usar `PaymentMethodService::registerPaymentMethod()` | Se guarda o actualiza el método. |
| Listar métodos | Usar `listPaymentMethods()` por `ownerReference` | Solo aparecen métodos activos del dueño correcto. |
| Marcar default | Usar `setDefaultPaymentMethod()` | Solo queda un método default por dueño/proveedor/contexto. |
| Desactivar método | Usar `deactivatePaymentMethod()` | El método deja de aparecer como activo. |
| Aislamiento por dueño | Crear métodos para dos usuarios distintos | Un usuario no ve métodos del otro. |
| Aislamiento por contexto | Probar dos `credentialContext` distintos | Los métodos no se mezclan entre contextos. |

## Billing y suscripciones

| Caso | Pasos | Resultado esperado |
| --- | --- | --- |
| Crear producto | `BillingService::createProduct()` | Se crea producto o falla controladamente si el proveedor no lo soporta. |
| Crear precio | `BillingService::createPrice()` | Se crea precio o falla controladamente si el proveedor no lo soporta. |
| Crear checkout de pago | `createCheckout()` con modo pago único | Se obtiene sesión/URL válida. |
| Crear checkout de suscripción | `createCheckout()` con modo suscripción | Se obtiene sesión/URL válida. |
| Consultar checkout | `lookupCheckout()` | Se devuelve estado normalizado. |
| Consultar suscripción | `lookupSubscription()` | Se devuelve estado normalizado. |
| Cancelar suscripción | `cancelSubscription()` | Se cancela o queda marcada para cancelar. |
| Portal de cliente | `createBillingPortal()` | Se obtiene URL o error controlado si no se soporta. |

## Webhooks

| Caso | Pasos | Resultado esperado |
| --- | --- | --- |
| Firma válida | Enviar webhook real o firmado correctamente | Se procesa el evento. |
| Firma inválida | Enviar webhook alterado | Se rechaza sin mutar datos. |
| Payload inválido | Enviar payload incompleto | Se responde con error controlado. |
| Evento duplicado | Reenviar mismo evento | No se reprocesa. |
| Evento desconocido | Enviar tipo de evento no soportado | No rompe el flujo; falla o ignora de forma controlada. |
| Contexto de credenciales | Usar `/stag-herd/webhooks/{provider}/{credentialContext}` | El evento usa el contexto correcto. |

## Smoke tests automatizados

Antes de liberar, deben pasar:

```bash
composer validate --strict
composer install --prefer-dist --no-interaction
composer test
composer phpstan
vendor/bin/php-cs-fixer fix --dry-run --diff --verbose
composer audit
```

## Criterio de salida para v1.0.0

| Nivel | Requisito |
| --- | --- |
| Bloqueante | La instalación limpia funciona. |
| Bloqueante | Los providers documentados no fallan por configuración básica. |
| Bloqueante | Los flujos de pago principales están probados. |
| Bloqueante | Los métodos guardados funcionan por dueño y contexto. |
| Bloqueante | Billing/suscripciones tienen pruebas o fallan controladamente por proveedor. |
| Bloqueante | Webhooks validan firma, idempotencia y payload inválido. |
| Bloqueante | README, implementación, environment y matriz de soporte están alineados. |
| Release | Composer, PHPUnit, PHPStan, CS Fixer y audit pasan. |