<?php

namespace Equidna\StagHerd\Tests\Fakes\Repositories;

use Equidna\StagHerd\Contracts\PaymentRepository;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Domain\Payment;
use RuntimeException;

final class NullPaymentRepository implements PaymentRepository
{
    public function storeFromResult(PaymentRequestData $request, PaymentResultData $result): Payment
    {
        throw new RuntimeException('Not implemented.');
    }

    public function find(int|string $id): ?Payment
    {
        return null;
    }

    public function findByProviderPaymentId(string $provider, string $providerPaymentId): ?Payment
    {
        return null;
    }

    public function findByProviderOrderId(string $provider, string $providerOrderId): ?Payment
    {
        return null;
    }

    public function findByExternalReference(string $externalReference): ?Payment
    {
        return null;
    }

    public function updateFromResult(Payment $payment, PaymentResultData $result): Payment
    {
        throw new RuntimeException('Not implemented.');
    }
}
