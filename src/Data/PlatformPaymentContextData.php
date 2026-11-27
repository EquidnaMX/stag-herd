<?php

namespace Equidna\StagHerd\Data;

use Equidna\StagHerd\Support\MoneyFormatter;

final readonly class PlatformPaymentContextData
{
    /** @param array<string, mixed> $providerMetadata */
    public function __construct(
        public string $credentialContext = 'default',
        public ?string $sellerReference = null,
        public ?int $platformFeeAmount = null,
        public ?string $environment = null,
        public array $providerMetadata = [],
    ) {
        //
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $platformFeeAmount = $data['platform_fee_amount']
            ?? $data['platformFeeAmount']
            ?? null;

        return new self(
            credentialContext: (string) ($data['credential_context'] ?? $data['credentialContext'] ?? 'default'),
            sellerReference: isset($data['seller_reference'])
                ? (string) $data['seller_reference']
                : (isset($data['sellerReference']) ? (string) $data['sellerReference'] : null),
            platformFeeAmount: $platformFeeAmount !== null && $platformFeeAmount !== ''
                ? MoneyFormatter::fromDecimal($platformFeeAmount)
                : null,
            environment: isset($data['environment']) ? strtolower((string) $data['environment']) : null,
            providerMetadata: isset($data['provider_metadata']) && is_array($data['provider_metadata'])
                ? $data['provider_metadata']
                : (isset($data['providerMetadata']) && is_array($data['providerMetadata']) ? $data['providerMetadata'] : []),
        );
    }

    public function paypalSellerMerchantId(): ?string
    {
        return $this->providerMetadata['paypal']['seller_merchant_id']
            ?? $this->providerMetadata['paypal']['sellerMerchantId']
            ?? $this->sellerReference;
    }

    public function paypalPlatformAttributionId(): ?string
    {
        return $this->providerMetadata['paypal']['platform_attribution_id']
            ?? $this->providerMetadata['paypal']['platformAttributionId']
            ?? null;
    }

    public function stripeDestinationAccount(): ?string
    {
        return $this->providerMetadata['stripe']['destination_account']
            ?? $this->providerMetadata['stripe']['destinationAccount']
            ?? $this->providerMetadata['stripe']['transfer_data']['destination']
            ?? $this->providerMetadata['stripe']['transferData']['destination']
            ?? $this->sellerReference;
    }

    public function stripeOnBehalfOfAccount(): ?string
    {
        return $this->providerMetadata['stripe']['on_behalf_of']
            ?? $this->providerMetadata['stripe']['onBehalfOf']
            ?? null;
    }

    public function mercadoPagoSellerAccessToken(): ?string
    {
        return $this->providerMetadata['mercado_pago']['seller_access_token']
            ?? $this->providerMetadata['mercado_pago']['sellerAccessToken']
            ?? $this->providerMetadata['mercado_pago']['access_token']
            ?? $this->providerMetadata['mercado_pago']['accessToken']
            ?? null;
    }

    public function mercadoPagoSellerReference(): ?string
    {
        return $this->providerMetadata['mercado_pago']['seller_reference']
            ?? $this->providerMetadata['mercado_pago']['sellerReference']
            ?? $this->sellerReference;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'credential_context' => $this->credentialContext,
            'seller_reference' => $this->sellerReference,
            'platform_fee_amount' => $this->platformFeeAmount,
            'environment' => $this->environment,
            'provider_metadata' => $this->providerMetadata,
        ];
    }
}
