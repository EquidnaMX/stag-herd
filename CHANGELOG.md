# Changelog

All notable changes to `stag-herd` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Webhook payload validation framework for all providers
- Rate limiting middleware for webhook endpoints
- Audit logging for payment state transitions
- Database transaction wrapping for payment operations
- PayPal certificate URL domain whitelisting
- CI/CD pipeline with GitHub Actions
- Code coverage reporting with PCOV
- Database migration stubs for payment tables

### Changed

- Pinned `equidna/laravel-toolkit` dependency from wildcard to semantic versioning (`^1.0`)
- Enhanced webhook verification with timestamp validation
- Improved PHPDoc compliance across all classes

### Security

- Added cryptographic signature verification for all webhook providers
- Implemented constant-time comparison for security-critical operations
- Added SSRF protection for PayPal webhook certificate validation

## [1.0.0] - TBD

### Added

- Initial release
- Multi-gateway payment adapter system
- Support for 7 payment providers: Stripe, PayPal, Mercado Pago, Conekta, Kueski, Openpay, Clip
- Webhook verification for all supported providers
- Payment lifecycle event system (PaymentApproved, PaymentRejected, PaymentLinkGenerated)
- Fluent payment builder API
- PSR-12 compliant codebase
- PHPStan level 6 static analysis
- Comprehensive test suite with 30+ tests

### Security

- Webhook signature verification using provider-specific algorithms
- Replay attack prevention with timestamp validation
- Constant-time hash comparison

[Unreleased]: https://github.com/EquidnaMX/stag-herd/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/EquidnaMX/stag-herd/releases/tag/v1.0.0
