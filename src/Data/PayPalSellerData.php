<?php

namespace Equidna\StagHerd\Data;

class PayPalSellerData
{
    /**
     * @param array<int, string> $permissions
     * @param array<int, string> $capabilities
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $sellerMerchantId,
        public readonly ?string $trackingId = null,
        public readonly ?string $ownerReference = null,
        public readonly ?string $accountStatus = null,
        public readonly ?string $consentStatus = null,
        public readonly array $permissions = [],
        public readonly array $capabilities = [],
        public readonly array $raw = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'seller_merchant_id' => $this->sellerMerchantId,
            'tracking_id' => $this->trackingId,
            'owner_reference' => $this->ownerReference,
            'account_status' => $this->accountStatus,
            'consent_status' => $this->consentStatus,
            'permissions' => $this->permissions,
            'capabilities' => $this->capabilities,
            'raw' => $this->raw,
        ];
    }
}
