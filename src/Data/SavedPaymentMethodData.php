<?php

namespace Equidna\StagHerd\Data;

final readonly class SavedPaymentMethodData
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public int|string $id,
        public string $provider,
        public string $credentialContext,
        public string $ownerReference,
        public string $providerCustomerId,
        public string $providerPaymentMethodId,
        public string $type = 'card',
        public ?string $fingerprint = null,
        public ?string $displayName = null,
        public ?string $brand = null,
        public ?string $last4 = null,
        public ?int $expMonth = null,
        public ?int $expYear = null,
        public bool $isDefault = false,
        public string $status = 'active',
        public ?string $attachedAt = null,
        public ?string $detachedAt = null,
        public ?string $lastUsedAt = null,
        public int $providerEventCreatedAt = 0,
        public array $payload = [],
    ) {
        //
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            provider: strtolower((string) ($data['provider'] ?? '')),
            credentialContext: (string) ($data['credential_context'] ?? 'default'),
            ownerReference: (string) ($data['owner_reference'] ?? ''),
            providerCustomerId: (string) ($data['provider_customer_id'] ?? ''),
            providerPaymentMethodId: (string) ($data['provider_payment_method_id'] ?? ''),
            type: strtolower((string) ($data['type'] ?? 'card')),
            fingerprint: self::nullableString($data['fingerprint'] ?? null),
            displayName: self::nullableString($data['display_name'] ?? null),
            brand: self::nullableString($data['brand'] ?? null),
            last4: self::nullableString($data['last4'] ?? null),
            expMonth: isset($data['exp_month']) ? (int) $data['exp_month'] : null,
            expYear: isset($data['exp_year']) ? (int) $data['exp_year'] : null,
            isDefault: (bool) ($data['is_default'] ?? false),
            status: strtolower((string) ($data['status'] ?? 'active')),
            attachedAt: self::nullableString($data['attached_at'] ?? null),
            detachedAt: self::nullableString($data['detached_at'] ?? null),
            lastUsedAt: self::nullableString($data['last_used_at'] ?? null),
            providerEventCreatedAt: (int) ($data['provider_event_created_at'] ?? 0),
            payload: isset($data['payload']) && is_array($data['payload'])
                ? $data['payload']
                : [],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'credential_context' => $this->credentialContext,
            'owner_reference' => $this->ownerReference,
            'provider_customer_id' => $this->providerCustomerId,
            'provider_payment_method_id' => $this->providerPaymentMethodId,
            'type' => $this->type,
            'fingerprint' => $this->fingerprint,
            'display_name' => $this->displayName,
            'brand' => $this->brand,
            'last4' => $this->last4,
            'exp_month' => $this->expMonth,
            'exp_year' => $this->expYear,
            'is_default' => $this->isDefault,
            'status' => $this->status,
            'attached_at' => $this->attachedAt,
            'detached_at' => $this->detachedAt,
            'last_used_at' => $this->lastUsedAt,
            'provider_event_created_at' => $this->providerEventCreatedAt,
            'payload' => $this->payload,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
