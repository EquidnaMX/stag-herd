<?php

namespace Equidna\StagHerd\Data;

class PayPalSellerOnboardingData
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $integration
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
        public readonly array $query = [],
        public readonly array $integration = [],
        public readonly array $permissions = [],
        public readonly array $capabilities = [],
        public readonly array $raw = [],
    ) {
    }
}
