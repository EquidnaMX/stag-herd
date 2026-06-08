<?php

namespace Equidna\StagHerd\Contracts;

use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Domain\Payment;
use Illuminate\Pagination\LengthAwarePaginator;

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

    public function updateFromResult(
        Payment $payment,
        PaymentResultData $result,
    ): Payment;

    public function paginate(
        ?string $search = null,
        int $perPage = 10,
    ): LengthAwarePaginator;

    public function findForDisplay(int|string $id): ?object;
}
