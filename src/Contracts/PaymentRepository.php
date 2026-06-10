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
     * Busca por el ID interno del pago.
     */
    public function find(int|string $id): ?Payment;

    /**
     * Busca por el ID del pago generado por el provider.
     */
    public function findByProviderPaymentId(
        string $provider,
        string $providerPaymentId,
    ): ?Payment;

    /**
     * Busca por cualquier referencia importante del provider.
     *
     * Puede ser provider_payment_id, provider_order_id,
     * provider_transaction_id, provider_refund_id, etc.
     */
    public function findByProviderReference(
        string $provider,
        string $reference,
    ): ?Payment;

    public function updateFromResult(
        Payment $payment,
        PaymentResultData $result,
    ): Payment;
}
