---
name: Tracking Epic
about: Track a set of related tasks with acceptance criteria and links
title: "[Epic] Payment/Webhook Hardening"
labels: enhancement, epic
assignees: ""
---

## Goal

Harden webhook verification and operational safeguards across all supported providers.

## Scope

- Implement strong signature verification for Conekta
- Avoid Openpay JSON canonicalization drift and add tests
- Verify Kueski header names/algorithm; add tests
- Pin dependencies to stable ranges
- Add CI (phpunit, phpstan, optional Infection)
- Expand docs with architecture and edge-case guidance

## Acceptance Criteria

- All providers have unit tests for signature verification (happy/error paths)
- CI green with static analysis
- README updated with security and configuration details
- No wildcards in composer dependencies for runtime packages

## Sub-Issues

- [ ] Conekta signature verification
- [ ] Openpay signature canonicalization + tests
- [ ] Kueski headers/algorithm validation + tests
- [ ] Dependency pinning
- [ ] CI pipeline
- [ ] Documentation updates

## Notes

Link to sample payloads, vendor docs, and any relevant internal references.
