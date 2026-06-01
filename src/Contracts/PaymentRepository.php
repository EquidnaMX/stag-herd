<?php

namespace Equidna\StagHerd\Contracts;

use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Domain\Payment;

interface PaymentRepository
{
    /**
     * Store a payment from a provider result.
     */
    public function storeFromResult(
        PaymentRequestData $request,
        PaymentResultData $result,
    ): Payment;

    /**
     * Find a payment by local ID.
     */
    public function find(int|string $id): ?Payment;

    /**
     * Find a payment by host reference.
     */
    public function findByExternalReference(string $externalReference): ?Payment;

    /**
     * Find a payment by provider reference.
     */
    public function findByProviderReference(
        string $provider,
        string $reference,
    ): ?Payment;

    /**
     * Update a payment from a provider result.
     */
    public function updateFromResult(
        Payment $payment,
        PaymentResultData $result,
    ): Payment;
}
