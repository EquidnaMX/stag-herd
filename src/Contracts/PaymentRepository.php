<?php

namespace Equidna\StagHerd\Contracts;

use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Domain\Payment;

interface PaymentRepository
{
    public function storeFromResult(
        PaymentRequestData $request,
        PaymentResultData $result,
    ): Payment;

    /**
     * Find a payment by its internal identifier.
     */
    public function find(int|string $id): ?Payment;

    /**
     * Find a payment by the provider payment identifier.
     */
    public function findByProviderPaymentId(
        string $provider,
        string $providerPaymentId,
    ): ?Payment;

    /**
     * Find a payment by the provider order identifier.
     */
    public function findByProviderOrderId(
        string $provider,
        string $providerOrderId,
    ): ?Payment;

    /**
     * Find a payment by the external reference stored in metadata.
     */
    public function findByExternalReference(string $externalReference): ?Payment;

    public function updateFromResult(
        Payment $payment,
        PaymentResultData $result,
    ): Payment;
}
