<?php

namespace Equidna\StagHerd\Data;

final readonly class ProviderReferencesData
{
    public function __construct(
        public ?string $providerPaymentId = null,
        public ?string $providerOrderId = null,
        public ?string $providerTransactionId = null,
        public ?string $providerRefundId = null,
    ) {
        //
    }

    public function toArray(): array
    {
        return [
            'provider_payment_id' => $this->providerPaymentId,
            'provider_order_id' => $this->providerOrderId,
            'provider_transaction_id' => $this->providerTransactionId,
            'provider_refund_id' => $this->providerRefundId,
        ];
    }
}
