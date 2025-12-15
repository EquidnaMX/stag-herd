## Overview

Describe the change, the problem it solves, and any context needed.

## Checklist

- [ ] Security: All webhook verifications impacted by changes are covered by tests
- [ ] Docs: README and config docs updated if behavior/config changed
- [ ] Backwards compatibility: No breaking public API changes (or documented/migration provided)
- [ ] Static analysis: `composer phpstan` passes
- [ ] Tests: `composer test` passes locally
- [ ] Mutation testing: Infection run shows no major regressions (if applicable)

## Payment/Webhook Hardening (Package-Specific)

- [ ] Conekta: Signature verification implemented (RSA/HMAC) and tested
- [ ] Openpay: Signature verification avoids JSON re-encoding drift; realistic samples tested
- [ ] Kueski: Header names and HMAC algorithm verified against vendor spec; tested
- [ ] Stripe: Timestamp tolerance and signature parsing tested
- [ ] PayPal: OAuth token caching and verify-webhook-signature flow tested
- [ ] Mercado Pago: Manifest computation string tested (ts/request-id/data.id variants)
- [ ] Idempotency: Duplicate detection via cache tested with configurable TTL

## Dependency & CI

- [ ] Dependencies pinned to stable ranges (no wildcards)
- [ ] CI includes phpunit + phpstan (+ optional Infection) and runs on PRs

## Risk Assessment

- [ ] Areas touching payment flows audited for failure modes and logged appropriately
- [ ] Errors use domain exceptions consistently
- [ ] Sensitive data (secrets/tokens) not logged

## Testing Notes

Add any special instructions for reviewers to run tests or reproduce scenarios.
