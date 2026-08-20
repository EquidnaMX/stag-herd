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
}
