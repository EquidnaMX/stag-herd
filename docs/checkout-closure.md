# Closing pending one-time checkouts

`Application\CheckoutClosureService::close(CheckoutClosureData)` is an additive,
server-side operation. Supply only persisted merchant-owned references, external
purchase reference and its credential context. It never captures, refunds or
deletes provider movements. No HTTP route is added.

- Stripe: verify purchase attribution and payment mode, expire an open session,
  accept only `expired` + `unpaid`. Complete asynchronous sessions remain blocked.
- PayPal Orders v2: verify a single CAPTURE purchase unit and no captures or
  authorizations or automatic completion instructions. There is no order cancellation endpoint. An unpaid order can
  be closed **locally** only if `consumerCaptureBlocked=true`: the consumer must
  serialize every capture path with the purchase lock and durably cancel/delete
  that purchase before releasing the lock. The result explicitly reports
  `requiresCaptureBlock`; it never claims the PayPal order was remotely canceled.
- Mercado Pago Checkout Pro: verify the preference's external reference, expire
  and re-read it, paginate associated payments and re-read each before requesting
  cancellation of pending/in-process/authorized payments. Approved or uncertain
  payments require reconciliation; no refund is attempted. A consumer may supply
  `settledPaymentId` only for an already completed/refunded local purchase: that
  exact previously settled payment is preserved while closing its reusable
  preference. Any other approved payment still blocks closure. Expiration alone is
  never sufficient proof that existing payments are closed.

Network failures propagate. Remote effects cannot be rolled back by a database
transaction: retry by re-reading provider state. Retain local history when a
closure is not confirmed. Custom Stripe/Mercado Pago gateways may implement the
new optional capability interfaces; older implementations remain compatible and
return a non-closed result when the required capability is unavailable.

Callers must authorize this administrative operation, isolate tenants, fence
checkout creation/capture and revalidate initiated accesses before purging.
There are no public exports, logging of payloads, automatic refunds or claims of
live-provider verification. Automated tests use provider boundary doubles.

References: [Stripe expiration](https://docs.stripe.com/api/checkout/sessions/expire),
[PayPal Orders v2](https://developer.paypal.com/api/orders/v2/),
[Mercado Pago preferences](https://www.mercadopago.com.mx/developers/es/reference/online-payments/checkout-pro-preferences/update-preference/put).
