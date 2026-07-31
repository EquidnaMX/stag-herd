<?php

namespace Equidna\StagHerd\Data;

use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;

final readonly class PaymentLookupData
{
    public function __construct(
        public string $provider,
        public ?string $method = null,
        public ?string $paymentId = null,
        public ?string $providerPaymentId = null,
        public ?string $providerOrderId = null,
        public string $credentialContext = 'default',
    ) {
        $this->validate();
    }

    /** @return 'payment_id'|'provider_payment_id'|'provider_order_id' */
    public function lookupType(): string
    {
        if ($this->paymentId !== null) {
            return 'payment_id';
        }

        if ($this->providerPaymentId !== null) {
            return 'provider_payment_id';
        }

        if ($this->providerOrderId !== null) {
            return 'provider_order_id';
        }

        throw InvalidPaymentPayloadException::missingField(
            'paymentId, providerPaymentId or providerOrderId'
        );
    }

    public function lookupValue(): string
    {
        return match ($this->lookupType()) {
            'payment_id' => $this->paymentId,
            'provider_payment_id' => $this->providerPaymentId,
            'provider_order_id' => $this->providerOrderId,
        };
    }

    private function validate(): void
    {
        $criteria = array_filter([
            $this->paymentId,
            $this->providerPaymentId,
            $this->providerOrderId,
        ], fn (?string $value) => $value !== null && $value !== '');

        if (count($criteria) === 0) {
            throw InvalidPaymentPayloadException::missingField(
                'paymentId, providerPaymentId or providerOrderId'
            );
        }

        if (count($criteria) > 1) {
            throw InvalidPaymentPayloadException::invalidField(
                'lookup',
                'Only one lookup criterion is allowed: paymentId, providerPaymentId or providerOrderId.'
            );
        }
    }
}
