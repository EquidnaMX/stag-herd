# Configuracion de ambientes

Esta guia define que variables debe configurar la aplicacion host antes de probar o liberar StagHerd. No guardes credenciales reales en este repositorio; usa `.env.example` solo como plantilla.

## Comun a todos los ambientes

| Variable | Uso | Recomendacion |
| --- | --- | --- |
| `STAG_HERD_ROUTE_PREFIX` | Prefijo de rutas webhook | Mantener `stag-herd` salvo que el host ya lo use |
| `STAG_HERD_PAYMENT_MODEL` | Modelo Eloquent de pagos del host | Obligatorio en la app integradora |
| `STAG_HERD_AUDIT_CHANNEL` | Canal de logs | Usar un canal centralizado en sandbox/produccion |
| `WEBHOOK_IDEMPOTENCY_TTL` | Ventana de deduplicacion | `604800` segundos como base |
| `WEBHOOK_RATE_LIMIT` | Limite por IP/minuto | `60` para iniciar; ajustar con trafico real |
| `STAG_HERD_CLEANUP_ENABLED` | Limpieza programada | `true` en sandbox/produccion |

## Local

| Area | Valor sugerido |
| --- | --- |
| Proveedores | Habilitar solo el proveedor bajo prueba |
| URLs publicas | Usar tunnel HTTPS estable para webhooks y retornos |
| Cache | Usar cache persistente si se prueba idempotencia |
| Logs | `STAG_HERD_AUDIT_CHANNEL=stack` o canal local dedicado |
| Cleanup | Puede quedar `false` durante pruebas manuales cortas |

## Sandbox / staging

| Proveedor | Variables obligatorias |
| --- | --- |
| PayPal | `PAYPAL_SANDBOX=true`, `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`, `PAYPAL_WEBHOOK_ID`, `PAYPAL_RETURN_ROUTE`, `PAYPAL_CANCEL_ROUTE` |
| Clip | `CLIP_API_KEY`, `CLIP_API_BASE_URL=https://api-gw.payclip.com`, `CLIP_WEBHOOK_URL`, `CLIP_SUCCESS_URL`, `CLIP_ERROR_URL`, `CLIP_DEFAULT_URL` |
| Conekta | `CONEKTA_API_SECRET`, `CONEKTA_WEBHOOK_PUBLIC_KEY` |
| Stripe / Google Pay | `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` |
| Mercado Pago | `MERCADOPAGO_ACCESS_TOKEN`, `MERCADOPAGO_WEBHOOK_SECRET` |
| Openpay | `OPENPAY_MERCHANT_ID`, `OPENPAY_PRIVATE_KEY`, `OPENPAY_SANDBOX=true`, `OPENPAY_WEBHOOK_SECRET` |

## Produccion

| Control | Requisito |
| --- | --- |
| Credenciales | Usar llaves live/productivas; nunca reutilizar sandbox |
| Webhooks | Registrar endpoints HTTPS finales con cada proveedor |
| Conekta | Reemplazar cualquier `CONEKTA_WEBHOOK_SECRET`; la verificacion usa `CONEKTA_WEBHOOK_PUBLIC_KEY` |
| PayPal | Usar `PAYPAL_SANDBOX=false` y confirmar que el webhook id pertenece al app live |
| Clip | Confirmar que `CLIP_WEBHOOK_URL` apunta al dominio publico final |
| Logs | No guardar payloads completos; usar provider, event/request id, hash y status |
| Cache | Usar Redis/Memcached/base compartida para idempotencia entre instancias |
| Alertas | Alertar por picos de 401/500 en `/stag-herd/{provider}` |

## Secuencia de actualizacion

1. Copiar `.env.example` a la aplicacion host y completar solo los proveedores activos.
2. Publicar o revisar `config/stag-herd.php` en la app host.
3. Confirmar que `STAG_HERD_PAYMENT_MODEL` o `config('stag-herd.payment_model')` apunta al modelo real.
4. Registrar URLs de webhook y retorno en cada dashboard de proveedor.
5. Ejecutar `php artisan config:clear` y `php artisan route:clear` en el host.
6. Ejecutar la matriz en `docs/sandbox-test-matrix.md`.
7. Congelar credenciales productivas y repetir los smoke tests con montos minimos antes de liberar.
