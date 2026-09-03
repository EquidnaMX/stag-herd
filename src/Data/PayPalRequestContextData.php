<?php

namespace Equidna\StagHerd\Data;

final readonly class PayPalRequestContextData
{
    /** @param array<string, mixed> $externalMetadata */
    public function __construct(
        public string $credentialContext = 'default',
        public ?string $sellerMerchantId = null,
        public ?string $platformAttributionId = null,
        public ?string $environment = null,
        public array $externalMetadata = [],
    ) {
        //
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return self::fromPlatformContext(
            PlatformPaymentContextData::fromArray($data),
        );
    }

    public static function fromPlatformContext(PlatformPaymentContextData $context): self
    {
        return new self(
            credentialContext: $context->credentialContext,
            sellerMerchantId: $context->paypalSellerMerchantId(),
            platformAttributionId: $context->paypalPlatformAttributionId(),
            environment: $context->environment,
            externalMetadata: $context->providerMetadata['paypal']['external_metadata'] ?? [],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'credential_context' => $this->credentialContext,
            'seller_merchant_id' => $this->sellerMerchantId,
            'platform_attribution_id' => $this->platformAttributionId,
            'environment' => $this->environment,
            'external_metadata' => $this->externalMetadata,
        ];
    }
}
