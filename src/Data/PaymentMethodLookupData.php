<?php

namespace Equidna\StagHerd\Data;

use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;

final readonly class PaymentMethodLookupData
{
    public function __construct(
        public string $provider,
        public string $ownerReference,
        public string $credentialContext = 'default',
        public ?string $providerPaymentMethodId = null,
    ) {
        //
    }

    public function requireProviderPaymentMethodId(): string
    {
        $providerPaymentMethodId = $this->providerPaymentMethodId !== null
            ? trim($this->providerPaymentMethodId)
            : '';

        if ($providerPaymentMethodId === '') {
            throw InvalidPaymentPayloadException::missingField(
                'provider_payment_method_id'
            );
        }

        return $providerPaymentMethodId;
    }
}
