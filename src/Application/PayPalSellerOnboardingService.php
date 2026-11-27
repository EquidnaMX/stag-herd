<?php

namespace Equidna\StagHerd\Application;

use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Contracts\PayPalSellerRepository;
use Equidna\StagHerd\Data\PayPalRequestContextData;
use Equidna\StagHerd\Data\PayPalSellerData;
use Equidna\StagHerd\Data\PayPalSellerOnboardingData;
use Equidna\StagHerd\Events\PayPalSellerOnboarded;
use Equidna\StagHerd\Support\CredentialContextManager;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final readonly class PayPalSellerOnboardingService
{
    public function __construct(
        private PayPalGateway $gateway,
        private PayPalSellerRepository $sellers,
        private CredentialContextManager $credentials,
    ) {}

    /**
     * @param array<string, mixed> $query
     */
    public function complete(
        string $sellerMerchantId,
        ?string $partnerMerchantId = null,
        ?string $trackingId = null,
        ?string $ownerReference = null,
        ?PayPalRequestContextData $context = null,
        array $query = [],
    ): PayPalSellerData {
        $sellerMerchantId = trim($sellerMerchantId);
        $partnerMerchantId = trim((string) (
            $partnerMerchantId
            ?: config('stag-herd.providers.paypal.credentials.partner_merchant_id')
        ));

        if ($sellerMerchantId === '') {
            throw new InvalidArgumentException('Missing PayPal seller merchant id.');
        }

        if ($partnerMerchantId === '') {
            throw new InvalidArgumentException('Missing PayPal partner merchant id.');
        }

        $context ??= new PayPalRequestContextData();

        $integration = $this->credentials->run(
            'paypal',
            $context->credentialContext,
            fn(): array => $this->gateway->getMerchantIntegration(
                partnerMerchantId: $partnerMerchantId,
                sellerMerchantId: $sellerMerchantId,
                context: $context,
            ),
        );

        $onboarding = new PayPalSellerOnboardingData(
            sellerMerchantId: $sellerMerchantId,
            trackingId: $trackingId,
            ownerReference: $ownerReference,
            accountStatus: $this->nullableString(data_get($integration, 'account_status')),
            consentStatus: $this->nullableString(data_get($integration, 'consent_status')),
            query: $query,
            integration: $integration,
            permissions: $this->stringList(data_get($integration, 'permissions')),
            capabilities: $this->stringList(data_get($integration, 'capabilities')),
            raw: [
                'query' => $query,
                'integration' => $integration,
            ],
        );

        $seller = $this->sellers->saveOnboardingResult($onboarding);

        Event::dispatch(new PayPalSellerOnboarded($seller, $onboarding));

        return $seller;
    }

    /** @return array<int, string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn(mixed $item): ?string => is_scalar($item) ? trim((string) $item) : null,
                $value,
            ),
            static fn(?string $item): bool => $item !== null && $item !== '',
        ));
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
