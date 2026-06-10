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
     * Busca por las referencias principales persistidas del provider.
     *
     * En la persistencia interna del paquete solo se buscan:
     * - provider_payment_id
     * - provider_order_id
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
