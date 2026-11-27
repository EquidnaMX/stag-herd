<?php

namespace Equidna\StagHerd\Tests\Fakes\Providers;

use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\ProviderReferencesData;
use Equidna\StagHerd\Contracts\PaymentProvider;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\RefundRequestData;
use RuntimeException;

class FakeWebhookPaymentProvider implements PaymentProvider
{
    public function getName(): string
    {
        return 'mercado_pago';
    }

    public function getMethods(): array
    {
        return ['card'];
    }

    public function createPayment(PaymentRequestData $request): PaymentResultData
    {
        throw new RuntimeException('Not implemented.');
    }

    public function lookupPayment(PaymentLookupData $request): PaymentResultData
    {
        return PaymentResultData::approved(
            provider: 'mercado_pago',
            method: 'card',
            providerStatus: 'approved',
            references: new ProviderReferencesData(providerPaymentId: '123'),
            amount: 12000,
            currency: 'MXN',
        );
    }

    public function cancelPayment(PaymentCancellationData $request): PaymentResultData
    {
        throw new RuntimeException('Not implemented.');
    }

    public function refundPayment(RefundRequestData $request): PaymentResultData
    {
        throw new RuntimeException('Not implemented.');
    }
}
