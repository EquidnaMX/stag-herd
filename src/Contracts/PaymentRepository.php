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

    public function find(int|string $id): ?Payment;

    public function findByExternalReference(string $externalReference): ?Payment;

    public function findByProviderReference(
        string $provider,
        string $reference,
    ): ?Payment;

    public function findByAnyProviderReference(
        string $provider,
        array $references,
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
