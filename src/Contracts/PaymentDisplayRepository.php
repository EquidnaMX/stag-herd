<?php

namespace Equidna\StagHerd\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface PaymentDisplayRepository
{
    public function paginateForDisplay(
        ?string $search = null,
        int $perPage = 10,
    ): LengthAwarePaginator;

    public function findForDisplay(int|string $id): ?object;
}
