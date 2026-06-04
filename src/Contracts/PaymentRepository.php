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
     *
     * En un sistema puede ser external_reference.
     * En otro sistema puede mapearse a id_order.
     */
    public function findByExternalReference(string $externalReference): ?Payment;

    /**
     * Find a payment by one provider reference.
     *
     * El repositorio decide dónde buscarlo:
     * - provider_payment_id
     * - method_id
     * - method_data
     * - metadata
     * - etc.
     */
    public function findByProviderReference(
        string $provider,
        string $reference,
    ): ?Payment;

    /**
     * Find a payment by multiple possible provider references.
     *
     * Ejemplo:
     * [
     *     'provider_payment_id' => '123',
     *     'provider_order_id' => 'ORD-456',
     *     'provider_transaction_id' => 'TX-789',
     *     'external_reference' => 'ORDER-10',
     * ]
     *
     * Cada implementación decide cómo mapear estas referencias
     * a sus columnas reales.
     *
     * @param array<string, mixed> $references
     */
    public function findByAnyProviderReference(
        string $provider,
        array $references,
    ): ?Payment;

    /**
     * Update a payment from a provider result.
     */
    public function updateFromResult(
        Payment $payment,
        PaymentResultData $result,
    ): Payment;
}
