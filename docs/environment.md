# Configuración de ambientes

Esta guía define las variables de entorno que debe configurar la aplicación host para usar StagHerd.

No guardes credenciales reales en este repositorio. Usa `.env.example` solo como plantilla y configura los valores reales en el `.env` del sistema host.

## Reglas generales

- Configura solo los proveedores que el host vaya a usar.
- Usa credenciales sandbox/local durante desarrollo.
- Usa credenciales live/productivas solo en producción.
- Después de cambiar configuración, limpia la caché de Laravel.
- No hardcodees secretos en código, config publicada o documentación.
- Las variables no habilitan proveedores: los proveedores de pago se habilitan
  en `config/stag-herd.php`. Configura solo los que estén habilitados.

## Configuración común

Variables de StagHerd:

```env
STAG_HERD_WEBHOOK_IDEMPOTENCY_DRIVER=database
STAG_HERD_WEBHOOK_IDEMPOTENCY_TTL=86400
```

Drivers esperados para idempotencia:

```text
database
redis
```

Recomendación:

| Ambiente | Driver recomendado |
| --- | --- |
| Local | `database` |
| Sandbox / staging | `database` o `redis` |
| Producción | `redis` o base compartida entre instancias |

Si tu aplicación corre en más de una instancia, la idempotencia debe usar un almacenamiento compartido.

## Rutas

Las rutas se configuran en `config/stag-herd.php`.

Las rutas públicas de métodos de pago guardados están deshabilitadas por defecto.
Si el host las habilita, debe usar middleware de autenticación/autorización que
derive o valide `owner_reference` contra el principal autenticado. El middleware
`api` por sí solo no hace esa validación. Consulta
`docs/release-closure-checklist.md`.

Pagos:

```php
'payments' => [
    'routes' => [
        'enabled' => true,
        'prefix' => 'stag-herd/payments',
        'middleware' => ['api'],
    ],
],
```

Métodos de pago guardados:

```php
'payment_methods' => [
    'routes' => [
        'enabled' => false,
        'prefix' => 'stag-herd/payments/payment-methods',
        'middleware' => ['api'],
    ],
],
```

Webhooks:

```php
'webhooks' => [
    'routes' => [
        'enabled' => true,
        'prefix' => 'stag-herd/webhooks',
        'middleware' => ['api'],
    ],
],
```

Endpoints principales de webhooks:

```text
POST /stag-herd/webhooks/{provider}/{credentialContext}
GET|POST /stag-herd/webhooks/mercado-pago
GET|POST /stag-herd/webhooks/paypal
GET|POST /stag-herd/webhooks/stripe
```

## Stripe

Variables:

```env
STRIPE_SECRET_KEY=
VITE_STRIPE_PUBLIC_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_API_VERSION=2026-02-25.clover
STRIPE_BASE_URI=https://api.stripe.com
```

Uso:

| Variable | Uso |
| --- | --- |
| `STRIPE_SECRET_KEY` | Llave privada para llamadas server-side. |
| `VITE_STRIPE_PUBLIC_KEY` | Llave pública para frontend. |
| `STRIPE_WEBHOOK_SECRET` | Secreto para validar webhooks. |
| `STRIPE_API_VERSION` | Versión de API usada por el adapter. |
| `STRIPE_BASE_URI` | Base URL de Stripe. Normalmente no necesitas cambiarla. |

Métodos soportados desde configuración:

```text
card
apple_pay
google_pay
tokenized_card
spei
```

## PayPal

Variables:

```env
VITE_PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=
PAYPAL_SECRET=
PAYPAL_WEBHOOK_ID=
PAYPAL_ENVIRONMENT=sandbox
PAYPAL_BASE_URI=https://api-m.sandbox.paypal.com
```

Uso:

| Variable | Uso |
| --- | --- |
| `VITE_PAYPAL_CLIENT_ID` | Client ID usado por frontend y configuración del proveedor. |
| `PAYPAL_CLIENT_SECRET` | Secreto principal para llamadas server-side. |
| `PAYPAL_SECRET` | Fallback de compatibilidad si no se define `PAYPAL_CLIENT_SECRET`. |
| `PAYPAL_WEBHOOK_ID` | ID del webhook registrado en PayPal. |
| `PAYPAL_ENVIRONMENT` | Ambiente PayPal. Ejemplo: `sandbox` o `live`. |
| `PAYPAL_BASE_URI` | Base URL de PayPal. |

Para sandbox:

```env
PAYPAL_ENVIRONMENT=sandbox
PAYPAL_BASE_URI=https://api-m.sandbox.paypal.com
```

Para producción:

```env
PAYPAL_ENVIRONMENT=live
PAYPAL_BASE_URI=https://api-m.paypal.com
```

Métodos soportados desde configuración:

```text
paypal
tokenized_card
```

## Mercado Pago

Variables:

```env
MERCADO_PAGO_ACCESS_TOKEN=
VITE_MERCADO_PAGO_PUBLIC_KEY=
MERCADO_PAGO_WEBHOOK_SECRET=
MERCADO_PAGO_BASE_URI=https://api.mercadopago.com
```

Uso:

| Variable | Uso |
| --- | --- |
| `MERCADO_PAGO_ACCESS_TOKEN` | Token privado para llamadas server-side. |
| `VITE_MERCADO_PAGO_PUBLIC_KEY` | Llave pública para frontend. |
| `MERCADO_PAGO_WEBHOOK_SECRET` | Secreto para validar webhooks. |
| `MERCADO_PAGO_BASE_URI` | Base URL de Mercado Pago. Normalmente no necesitas cambiarla. |

Métodos soportados desde configuración:

```text
card
checkout_pro
tokenized_card
```

## Local

En local se recomienda:

| Área | Recomendación |
| --- | --- |
| Proveedores | Activar solo el proveedor que estés probando. |
| Webhooks | Usar un túnel HTTPS si necesitas recibir eventos reales. |
| Idempotencia | `database` suele ser suficiente. |
| Logs | Usar el canal normal de Laravel. |
| Credenciales | Usar sandbox/test keys. |

Después de cambiar `.env`:

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

## Sandbox / staging

En sandbox o staging se recomienda:

| Área | Recomendación |
| --- | --- |
| Credenciales | Usar llaves sandbox/test de cada proveedor. |
| Webhooks | Registrar endpoints HTTPS reales del ambiente. |
| Idempotencia | Usar `database` o `redis`. |
| Pruebas | Registrar la evidencia aplicable de `docs/sandbox-test-matrix.md`. |
| Datos | Usar montos mínimos y cuentas de prueba. |

Checklist:

- [ ] Configurar `.env` del proveedor activo.
- [ ] Publicar configuración.
- [ ] Ejecutar migraciones.
- [ ] Confirmar que las rutas existen con `php artisan route:list`.
- [ ] Registrar webhook en el dashboard del proveedor.
- [ ] Probar creación de pago.
- [ ] Probar webhook.
- [ ] Probar errores controlados.

## Producción

En producción se recomienda:

| Área | Requisito |
| --- | --- |
| Credenciales | Usar llaves live/productivas. |
| Webhooks | Registrar endpoints HTTPS finales. |
| Idempotencia | Usar almacenamiento compartido entre instancias. |
| Logs | No guardar secretos ni datos sensibles completos. |
| Alertas | Monitorear errores de proveedor, 401, 422 y 500. |
| Caché | Ejecutar `config:cache` después de validar configuración. |

Comandos recomendados después de configurar producción:

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan config:cache
```

## Secuencia recomendada de configuración

1. Instalar StagHerd en la aplicación host.
2. Publicar `config/stag-herd.php`.
3. Publicar y ejecutar migraciones si se usarán las tablas del paquete.
4. Activar solo los proveedores necesarios.
5. Configurar variables `.env`.
6. Limpiar caché de Laravel.
7. Revisar rutas con `php artisan route:list`.
8. Registrar webhooks en los dashboards de los proveedores.
9. Registrar evidencia sandbox para los flujos que se vayan a anunciar.
10. Pasar a credenciales productivas solo cuando el flujo esté validado.

## Validación rápida

```bash
php artisan config:clear
php artisan route:clear
php artisan route:list
php artisan migrate:status
```

Antes de liberar, usa `docs/release-closure-checklist.md` como criterio de
cierre y registra los resultados de los comandos definidos por el repositorio:

```bash
composer validate --strict
composer test
composer phpstan
vendor/bin/php-cs-fixer fix --dry-run --diff --verbose
composer audit
```
