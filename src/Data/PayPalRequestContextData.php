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
        return new self(
            credentialContext: (string) ($data['credential_context'] ?? $data['credentialContext'] ?? 'default'),
            sellerMerchantId: isset($data['seller_merchant_id'])
                ? (string) $data['seller_merchant_id']
                : (isset($data['sellerMerchantId']) ? (string) $data['sellerMerchantId'] : null),
            platformAttributionId: isset($data['platform_attribution_id'])
                ? (string) $data['platform_attribution_id']
                : (isset($data['platformAttributionId']) ? (string) $data['platformAttributionId'] : null),
            environment: isset($data['environment']) ? strtolower((string) $data['environment']) : null,
            externalMetadata: isset($data['external_metadata']) && is_array($data['external_metadata'])
                ? $data['external_metadata']
                : (isset($data['externalMetadata']) && is_array($data['externalMetadata']) ? $data['externalMetadata'] : []),
        );
    }

    public static function fromPaymentRequest(PaymentRequestData $request): self
    {
        return new self(
            credentialContext: $request->credentialContext,
            sellerMerchantId: $request->sellerMerchantId,
            platformAttributionId: $request->platformAttributionId,
            environment: $request->environment,
            externalMetadata: $request->externalMetadata,
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
