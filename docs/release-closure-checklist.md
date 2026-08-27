# Release Closure Checklist

## Release decision

This checklist closes the package **as it exists today**. It does not add
features, require provider parity, or turn every configured entry into a
release promise. PayPal Platforms remains in the repository but is explicitly
out of scope for this release.

**Do not release while any v1 blocker below is open.** Check an item only when
its linked evidence has been reviewed and the required release record exists.

## Closed scope

- [ ] The release notes state that the supported surface is the current,
  provider-specific implementation; parity between Stripe, PayPal, and Mercado
  Pago is not promised.
- [ ] The release notes state that PayPal Platforms / partner referrals,
  seller merchant routing, and platform fees are out of scope. Their code is
  retained and is not removed by this release.
- [ ] The release notes do not advertise a capability merely because a route,
  handler, gateway method, or configuration key exists.
- [ ] Any capability not listed as release-supported below is described as
  unsupported, provider-dependent, or deferred rather than implied.

## Current capability matrix

Status is intentionally descriptive, not a support commitment. “Code present”
means the repository contains an implementation path. “Unit evidence” means a
repository test exercises part of that path with fakes or mocks; it is not
provider sandbox proof.

| Area | Stripe | PayPal | Mercado Pago |
| --- | --- | --- | --- |
| Direct payments | Code present: `card`, `apple_pay`, `google_pay`, and `spei`. No provider-specific direct-payment test was found. | Code present: `paypal`. No provider-specific checkout/capture test was found. | Code present: `card` and `checkout_pro`. No provider-specific direct-payment test was found. |
| Saved/tokenized card charge | Code present: `tokenized_card`; registered methods can be resolved by owner, provider, and credential context. No end-to-end route or provider test was found. | Code present: `tokenized_card`; it can use a supplied token or resolve a stored method. No end-to-end route or provider test was found. | Code present: `tokenized_card`; a fresh Mercado Pago token is required even when a stored method is resolved. No end-to-end route or provider test was found. |
| Hosted billing checkout | Code present for payment and subscription modes. Unit evidence covers a subscription checkout mapping in `StripeBillingProviderTest`. | Subscription-only; exactly one line item is required. Payment-mode billing checkout is explicitly rejected. No direct unit test was found. | Subscription-only; exactly one line item is required. Payment-mode billing checkout is explicitly rejected. No direct unit test was found. |
| Products and prices | Code present for products and prices. No direct unit test was found. | Code present for catalog products and recurring plans; intervals are limited to day, week, month, or year. No direct unit test was found. | Product creation is explicitly unsupported; price creation creates a recurring preapproval plan and accepts day, week, month, or year. No direct unit test was found. |
| Subscription lookup and cancellation | Code present. Unit evidence covers cancellation scheduled at period end. | Code present; cancellation at period end is explicitly unsupported. No direct unit test was found. | Code present; cancellation at period end is explicitly unsupported. No direct unit test was found. |
| Customer billing portal | Code present. No direct unit test was found. | Not implemented by `PayPalBillingProvider` (it does not implement `CreatesCustomerPortal`). | Not implemented by `MercadoPagoBillingProvider` (it does not implement `CreatesCustomerPortal`). |
| Refunds | Handler code is present for the configured direct methods. No provider-specific refund test was found. | Handler code is present for checkout and tokenized cards. Cancellation of a PayPal order is explicitly unsupported. No provider-specific refund test was found. | Handler code is present for card, Checkout Pro, and tokenized card. No provider-specific refund test was found. |
| Webhooks | Parser unit evidence covers signature requirements, normalized checkout state, and event identity. Generic processing covers idempotency behavior. | Parser and controller unit evidence cover valid/invalid signature outcomes and an unsupported event. Generic processing covers idempotency behavior. | A parser is configured, but no Mercado Pago parser signature/normalization test was found. Generic processing uses a fake Mercado Pago parser, not the provider parser. |

### Evidence references

- Provider and method registration: `config/stag-herd.php`.
- Billing limits: `src/Infrastructure/Providers/Stripe/StripeBillingProvider.php`,
  `src/Infrastructure/Providers/PayPal/PayPalBillingProvider.php`, and
  `src/Infrastructure/Providers/MercadoPago/MercadoPagoBillingProvider.php`.
- Saved-method behavior: `src/Infrastructure/Providers/*/Handlers/*TokenizedCardHandler.php`.
- Existing focused tests: `tests/Unit/StripeBillingProviderTest.php`,
  `tests/Unit/StripeWebhookParserTest.php`,
  `tests/Unit/PayPalWebhookParserTest.php`,
  `tests/Unit/WebhookControllerTest.php`,
  `tests/Unit/ProcessPaymentWebhookTest.php`,
  `tests/Unit/RedisWebhookIdempotencyStoreTest.php`, and
  `tests/Unit/PaymentMethodServiceTest.php`.

## v1 blockers

### 1. Saved-payment-method route security — release decision required

The default saved-method routes are enabled under `api` middleware:

- `GET /stag-herd/payments/payment-methods`
- `POST /stag-herd/payments/payment-methods/default`
- `POST /stag-herd/payments/payment-methods/deactivate`

Their request objects authorize every request and accept
`owner_reference` from the client. The package therefore does not, by default,
bind that reference to an authenticated principal. This is a release blocker
for any host that exposes these routes.

- [ ] Record one explicit release decision: **the routes are disabled/not
  exposed**, or **the host configures authentication and authorization
  middleware that derives or verifies `owner_reference` against the current
  principal for all three routes**.
- [ ] For the chosen deployment model, retain review evidence that one owner
  cannot list, make default, or deactivate another owner’s method, including
  separate credential contexts.
- [ ] Confirm the published configuration does not describe default `api`
  middleware as sufficient authorization for saved-method routes.

Evidence: `routes/payments.php`, `config/stag-herd.php`, and the three request
classes in `src/Http/Requests/Payments/PaymentMethods/`. The existing
`PaymentMethodServiceTest` checks repository filtering behavior, but no route
authorization test was found.

### 2. Existing automated checks

- [ ] Record the result for the repository’s defined test command:
  `composer test`.
- [ ] Record the result for the repository’s defined static-analysis command:
  `composer phpstan`.
- [ ] Record the result for the CI style check:
  `vendor/bin/php-cs-fixer fix --dry-run --diff --verbose`.
- [ ] Record the result for the CI dependency check: `composer audit`.
- [ ] Confirm the CI matrix remains applicable to the declared PHP and
  Illuminate constraints in `composer.json`.

Existing CI defines PHPUnit coverage, PHPStan, PHP-CS-Fixer, and Composer audit
in `.github/workflows/ci.yml`. This checklist contains no assertion that any
of those commands has been run.

### 3. Evidence missing for current functionality

Only collect evidence for capabilities that will be represented as currently
supported in the release. Do not expand the feature set to fill matrix gaps.

- [ ] For each advertised direct-payment method, retain a provider sandbox or
  integration record showing the created provider reference and final
  normalized status.
- [ ] For each advertised saved-method flow, retain evidence of registration,
  owner/context isolation, charge, and deactivation. Include the Mercado Pago
  requirement for a fresh token during a tokenized-card charge.
- [ ] For each advertised billing capability, retain evidence of its
  provider-specific constraint: Stripe mode, PayPal and Mercado Pago
  subscription-only checkout, no scheduled cancellation for PayPal/Mercado
  Pago, no Mercado Pago product catalog, and Stripe-only billing portal.
- [ ] For each advertised webhook provider, retain evidence of valid signature
  handling, rejected invalid signatures, duplicate handling, and the deployed
  credential context. Mercado Pago specifically needs evidence for the real
  configured parser, not only generic processing with a fake parser.
- [ ] Record unsupported-operation outcomes where the release documents a
  limit, rather than treating the absence of a flow as an untested promise.

## Documentation, configuration, and environment consistency

- [ ] Reconcile the release-facing claims in `README.md`,
  `docs/implementation.md`, `docs/support-matrix.md`,
  `docs/environment.md`, `docs/sandbox-test-matrix.md`, and this checklist
  with `config/stag-herd.php`.
- [ ] Remove or qualify the existing “stable” / broad-provider wording when
  evidence is absent or a provider-specific limit applies. In particular,
  document that product creation and a customer portal are not portable across
  the three providers.
- [ ] Keep `.env.example` aligned with `config/stag-herd.php` and the
  environment guide. Confirm every example variable is consumed by the
  configuration, including the documented `STAG_HERD_WEBHOOK_IDEMPOTENCY_*`
  names.
- [ ] Ensure release instructions expose no credentials and explain that only
  enabled, configured providers are usable.
- [ ] Verify package metadata and release instructions are compatible: the
  README currently uses the development branch `dev-dev`, and the package has
  `minimum-stability: dev`. A published release must replace that branch with
  its actual version.

## Release criteria

- [ ] All v1 blockers above are checked with reviewable records.
- [ ] Release notes name the provider-specific scope and the explicit limits
  in the current capability matrix.
- [ ] The saved-method route decision is applied in the intended host
  deployment and has isolation evidence.
- [ ] Automated-check results are attached to the release record.
- [ ] Evidence is attached only for the capabilities the release claims; no
  provider-parity claim is introduced.
- [ ] PayPal Platforms remains excluded from the release promise without
  requiring its code to be removed.

## Definition of done

The package is ready to close when the release record demonstrates that its
documented, provider-specific surface is truthful; all advertised current
flows have proportionate automated and/or sandbox evidence; saved-method
routes are either not exposed or protected by a verified host authorization
model; documentation, configuration, and environment names agree; and no v1
blocker remains open.

## Follow-up after v1

These items are not v1 completion criteria unless the release chooses to
advertise them:

- Improve or add provider integration coverage beyond the flows claimed by the
  release.
- Rework public route authorization into a package-level policy, if the
  package should own identity-to-owner binding rather than delegate it to the
  host.
- Pursue provider parity, portable catalog semantics, or portals across
  providers.
- Remove, redesign, or document PayPal Platforms separately from this release.
