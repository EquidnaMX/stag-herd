# Changelog

All notable changes to `stag-herd` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-07-30

### Fixed

- Fixed `RouteNotFoundException` for PayPal and MercadoPago payments by adding default confirmation routes.
- Configurable `return_url` and `cancel_url` for PayPal and MercadoPago.
- Added `WebhookController::confirm` method to handle payment return redirection.

### Added

- Provider-neutral Billing capabilities for hosted checkout, products/prices, subscriptions, invoices and Customer Portal.
- Stripe Checkout support for `payment` and `subscription` modes using API version `2026-02-25.clover`.
- Request-scoped opaque credential contexts for payment, billing and webhook operations.
- Durable billing-resource and webhook-event persistence, with stale provider events ignored.
- Normalized `CheckoutCompleted`, `SubscriptionStatusChanged`, `InvoicePaid` and `InvoicePaymentFailed` events.
- Webhook payload validation framework for all providers
- Rate limiting middleware for webhook endpoints
- Audit logging for payment state transitions
- Database transaction wrapping for payment operations
- PayPal certificate URL domain whitelisting
- CI/CD pipeline with GitHub Actions
- Code coverage reporting with PCOV
- Database migration stubs for payment tables

### Changed

- Contextual webhooks are available at `/stag-herd/webhooks/{provider}/{credentialContext}`.
- Database-backed webhook idempotency is the default; Redis remains available as an explicit compatibility option.
- Pinned `equidna/laravel-toolkit` dependency from wildcard to semantic versioning (`^1.0`)
- Enhanced webhook verification with timestamp validation
- Improved PHPDoc compliance across all classes
- Handlers now receive `PaymentData` directly; removed legacy stdClass conversion.
- Payment fees are now configured per provider (e.g., `stag-herd.paypal.fee`) and mapped into `stag-herd.methods.*.fee`.
- Added `PaymentMethodDataNormalizer` under `Equidna\StagHerd\Support`.

### Removed

- Removed `PaymentFactory`; use `Payment::fromMethodID()` directly.
- Removed package-level `CASH` and `BASE` handlers; host apps must register their own handlers.

### Security

- Provider signatures are mandatory for contextual Stripe, PayPal and Mercado Pago webhooks.
- Webhook idempotency uses the real provider event ID and is no longer bounded by a Redis TTL.
- Added cryptographic signature verification for all webhook providers
- Implemented constant-time comparison for security-critical operations
- Added SSRF protection for PayPal webhook certificate validation

[Unreleased]: https://github.com/EquidnaMX/stag-herd/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/EquidnaMX/stag-herd/releases/tag/v1.0.0
