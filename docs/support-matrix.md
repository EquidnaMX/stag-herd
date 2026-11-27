# Matriz de implementación actual

Esta matriz describe rutas presentes en el código actual; no es una promesa de
estabilidad, disponibilidad comercial ni paridad entre proveedores. Antes de
anunciar una capacidad o liberar una versión, consulta
`docs/release-closure-checklist.md`. PayPal Platforms está fuera de alcance.

## Cómo leerla

| Estado | Significado |
| --- | --- |
| Configurado | El proveedor, método o adapter aparece en `config/stag-herd.php`. |
| Implementado | Existe una ruta de código para la operación, sujeta a configuración y credenciales. |
| No implementado | La operación lanza `UnsupportedOperationException` o el provider no implementa el contrato. |

Los proveedores de pago directo Stripe, PayPal y Mercado Pago están
deshabilitados por defecto. El host debe habilitarlos en la configuración
publicada; las variables de entorno no los habilitan.

## Pagos directos y métodos guardados

| Proveedor | Métodos configurados | Observaciones |
| --- | --- | --- |
| Stripe | `card`, `apple_pay`, `google_pay`, `tokenized_card`, `spei` | Implementados mediante handlers configurados. |
| PayPal | `paypal`, `tokenized_card` | Implementados mediante handlers configurados. |
| Mercado Pago | `card`, `checkout_pro`, `tokenized_card` | Implementados mediante handlers configurados. Una carga tokenizada requiere un token fresco incluso al resolver un método guardado. |
| Custom | Ninguno por defecto | El host puede registrar handlers propios. |
| Cash | `cash` | Método local habilitado por defecto para flujos manuales o internos. |

`PaymentMethodService` administra el registro, consulta, valor predeterminado y
desactivación de métodos guardados. Sus rutas públicas están deshabilitadas por
defecto. Si el host las habilita, debe agregar autenticación/autorización que
derive o valide `owner_reference` contra el usuario actual; `api` por sí solo no
hace esa asociación.

## Billing y suscripciones

| Capacidad | Stripe | PayPal | Mercado Pago |
| --- | --- | --- | --- |
| Checkout hospedado | Pago y suscripción | Solo suscripción; exactamente una línea | Solo suscripción; exactamente una línea |
| Consulta de checkout y suscripción | Implementado | Implementado sobre la suscripción | Implementado sobre la preapproval |
| Cancelación inmediata | Implementado | Implementado | Implementado |
| Cancelación al fin del período | Implementado | No implementado | No implementado |
| Crear producto | Implementado | Implementado | No implementado |
| Crear precio/plan | Implementado | Implementado como plan recurrente | Implementado como plan recurrente |
| Intervalos de plan | Delegado a Stripe | `day`, `week`, `month`, `year` | `day`, `week`, `month`, `year` |
| Portal de cliente | Implementado | No implementado | No implementado |

Las operaciones de billing no soportadas fallan con
`UnsupportedOperationException`; el host debe manejar ese resultado y no asumir
una interfaz portable entre proveedores.

## Webhooks y persistencia

| Área | Implementación actual |
| --- | --- |
| Webhooks | Hay parsers configurados para Stripe, PayPal y Mercado Pago. El procesamiento usa almacenamiento de idempotencia. |
| Idempotencia | `STAG_HERD_WEBHOOK_IDEMPOTENCY_DRIVER=redis` selecciona Redis; cualquier otro valor usa el store Eloquent/database. |
| Persistencia | Pagos y métodos de pago aceptan repositorios configurables; los recursos de billing usan el repositorio Eloquent registrado por el provider. |
| Rutas | Las rutas de pagos y webhooks se habilitan y prefijan desde `config/stag-herd.php`; las rutas públicas de métodos guardados están deshabilitadas por defecto. |

## Fuente y cierre

La fuente funcional de proveedores, métodos, rutas y variables es
`config/stag-herd.php`. Las rutas y contratos presentes no sustituyen la
evidencia de un flujo real. Para la decisión de release, evidencia requerida y
límites de pruebas, usa `docs/release-closure-checklist.md` y
`docs/sandbox-test-matrix.md`.
