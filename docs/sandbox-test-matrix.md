# Matriz de pruebas sandbox

Ejecutar despues de actualizar variables de ambiente y registrar webhooks en los dashboards de proveedor. Cada caso debe guardar evidencia: payment id local, method id del proveedor, status final, hash/request id de webhook y captura de dashboard cuando aplique.

## Prerrequisitos

| Item | Criterio |
| --- | --- |
| App host | `config:clear`, `route:clear` y paquete instalado desde la rama bajo prueba |
| HTTPS publico | Webhooks y return URLs accesibles desde internet |
| Cache | Backend persistente habilitado para idempotencia |
| Logs | Canal de auditoria visible y sin payloads completos |
| Modelo de pagos | `STAG_HERD_PAYMENT_MODEL` configurado y persistiendo `method_id`, `method_data`, `status` |

## Proveedores

| Proveedor | Caso | Pasos | Resultado esperado |
| --- | --- | --- | --- |
| PayPal | Orden aprobada y capturada | Crear pago PayPal, abrir approval link, aprobar con comprador sandbox, volver por `PAYPAL_RETURN_ROUTE` | Pago local pasa a `APPROVED`; `method_data.capture_id` y `capture_status=COMPLETED`; no se aprueba solo con `CHECKOUT.ORDER.APPROVED` |
| PayPal | Idempotencia de return URL | Recargar la URL de retorno de la misma orden | No se duplica la captura; se conserva `APPROVED`; respuesta sigue consistente |
| PayPal | Webhook capture completed | Enviar/recibir `PAYMENT.CAPTURE.COMPLETED` real | Webhook responde 200; si encuentra pago, confirma `APPROVED` y almacena `capture_id` |
| Clip | Checkout link creado | Crear pago Clip | Request usa `/checkout`; se guarda `payment_request_id` como `method_id` y link de pago |
| Clip | Webhook falsificado | POST manual a `/stag-herd/clip` con `payment_request_id` valido pero status falso | El paquete reconsulta `GET /checkout/{payment_request_id}`; no cambia a `APPROVED` si Clip no lo confirma |
| Clip | Pago completado | Pagar link en sandbox o flujo de prueba disponible | Webhook responde 200; pago local pasa a `APPROVED` solo si API Clip devuelve `COMPLETED` |
| Conekta | Digest RSA valido | Registrar webhook Conekta con public key real y emitir evento sandbox | Webhook responde 200; firma RSA valida con `CONEKTA_WEBHOOK_PUBLIC_KEY` |
| Conekta | Digest HMAC legacy | Reenviar payload con digest HMAC antiguo | Webhook responde 401; no muta el pago |
| Stripe / Google Pay | SDK disponible | Instalar limpio y resolver `StripeAdapter` con `STRIPE_SECRET` sandbox | No hay error `Class Stripe\\StripeClient not found`; SDK `stripe/stripe-php` cargado |
| Stripe / Google Pay | Webhook firmado | Enviar evento firmado con `STRIPE_WEBHOOK_SECRET` | Responde 200 solo con firma valida y respeta tolerancia |
| Mercado Pago | Webhook firmado | Enviar evento con `X-Signature` y `X-Request-Id` reales | Responde 200; deduplica por `data.id`; rechaza firma invalida |
| Openpay | Webhook firmado | Enviar evento con `Verification-Signature` o `Signature-Digest` | Responde 200 con firma valida; 401 con firma invalida |

## Smoke tests de regresion

| Area | Comando / accion | Criterio |
| --- | --- | --- |
| Dependencias | `composer install --no-dev --prefer-dist` en instalacion limpia | No falta `Stripe\\StripeClient` |
| Unit tests | `vendor/bin/phpunit --do-not-cache-result` | Suite en verde |
| Analisis estatico | `vendor/bin/phpstan analyse --memory-limit=2G` | Sin errores |
| Formato | `vendor/bin/php-cs-fixer fix --dry-run --diff --verbose` | Sin diffs |
| Seguridad | `composer audit` | Sin advisories |
| Logs | Revisar una ronda de webhooks | No aparecen payloads completos ni PII innecesaria |

## Criterio de salida

| Nivel | Requisito |
| --- | --- |
| Bloqueante | PayPal, Clip y Conekta pasan sus casos P0 |
| Alto | Instalacion limpia carga Stripe SDK y no hay 500 en webhooks habilitados |
| Operativo | Logs, idempotencia y rate limit observados en sandbox |
| Release | Smoke tests automatizados en verde y evidencias adjuntas al PR |
