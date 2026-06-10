<?php

namespace Equidna\StagHerd\Infrastructure\Persistence;

use Equidna\StagHerd\Contracts\PaymentDisplayRepository;
use Equidna\StagHerd\Infrastructure\Persistence\Models\StagHerdPayment;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentPaymentDisplayRepository implements PaymentDisplayRepository
{
    public function paginateForDisplay(
        ?string $search = null,
        int $perPage = 10,
    ): LengthAwarePaginator {
        $search = trim((string) $search);

        return StagHerdPayment::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    if (ctype_digit($search)) {
                        $query->orWhere('id', $search);
                    }

                    $query
                        ->orWhere('provider', 'like', "%{$search}%")
                        ->orWhere('method', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('provider_status', 'like', "%{$search}%")
                        ->orWhere('payer_reference', 'like', "%{$search}%")
                        ->orWhere('payer_email', 'like', "%{$search}%")
                        ->orWhere('provider_payment_id', 'like', "%{$search}%")
                        ->orWhere('provider_order_id', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findForDisplay(int|string $id): ?object
    {
        $payment = StagHerdPayment::query()->find($id);

        return $payment ? (object) $payment->toArray() : null;
    }
}
