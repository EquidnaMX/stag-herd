<?php

namespace Equidna\StagHerd\Data;

final readonly class PaymentMethodRegisterData
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $provider,
        public string $ownerReference,
        public string $providerCustomerId,
        public string $providerPaymentMethodId,
        public string $credentialContext = 'default',
        public string $type = 'card',
        public ?string $fingerprint = null,
        public ?string $displayName = null,
        public ?string $brand = null,
        public ?string $last4 = null,
        public ?int $expMonth = null,
        public ?int $expYear = null,
        public bool $isDefault = false,
        public string $status = 'active',
        public ?int $providerEventCreatedAt = null,
        public array $payload = [],
        public ?string $attachedAt = null,
        public ?string $detachedAt = null,
    ) {
        //
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider' => strtolower($this->provider),
            'credential_context' => $this->credentialContext,
            'owner_reference' => $this->ownerReference,
            'provider_customer_id' => $this->providerCustomerId,
            'provider_payment_method_id' => $this->providerPaymentMethodId,
            'type' => strtolower($this->type),
            'fingerprint' => $this->fingerprint,
            'display_name' => $this->displayName,
            'brand' => $this->brand,
            'last4' => $this->last4,
            'exp_month' => $this->expMonth,
            'exp_year' => $this->expYear,
            'is_default' => $this->isDefault,
            'status' => strtolower($this->status),
            'provider_event_created_at' => $this->providerEventCreatedAt,
            'payload' => $this->payload,
            'attached_at' => $this->attachedAt,
            'detached_at' => $this->detachedAt,
        ];
    }
}
