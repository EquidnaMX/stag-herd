<?php

namespace Equidna\StagHerd\Data;

final readonly class MercadoPagoRequestContextData
{
    /** @param array<string, mixed> $externalMetadata */
    public function __construct(
        public string $credentialContext = 'default',
        public ?string $sellerAccessToken = null,
        public ?string $sellerReference = null,
        public ?string $environment = null,
        public array $externalMetadata = [],
    ) {
        //
    }

    public static function fromPlatformContext(PlatformPaymentContextData $context): self
    {
        return new self(
            credentialContext: $context->credentialContext,
            sellerAccessToken: $context->mercadoPagoSellerAccessToken(),
            sellerReference: $context->mercadoPagoSellerReference(),
            environment: $context->environment,
            externalMetadata: $context->providerMetadata['mercado_pago']['external_metadata'] ?? [],
        );
    }
}
