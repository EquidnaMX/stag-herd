<?php

namespace Equidna\StagHerd\Data;

final readonly class PaymentMethodDeactivateData
{
    public function __construct(
        public string $provider,
        public string $ownerReference,
        public string $providerPaymentMethodId,
        public string $credentialContext = 'default',
    ) {
        //
    }

    public function toLookupData(): PaymentMethodLookupData
    {
        return new PaymentMethodLookupData(
            provider: $this->provider,
            ownerReference: $this->ownerReference,
            credentialContext: $this->credentialContext,
            providerPaymentMethodId: $this->providerPaymentMethodId,
        );
    }
}
