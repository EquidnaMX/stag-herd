<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Cash;

use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;

final class CashStatusMapper
{
    /**
     * Map a cash status to a normalized payment status.
     */
    public function map(?string $status): PaymentStatusEnum
    {
        return match (strtolower((string) $status)) {
            'approved',
            'paid',
            'completed' => PaymentStatusEnum::APPROVED,

            'pending',
            '' => PaymentStatusEnum::PENDING,

            'rejected',
            'declined' => PaymentStatusEnum::REJECTED,

            'canceled',
            'cancelled' => PaymentStatusEnum::CANCELED,

            'refunded' => PaymentStatusEnum::REFUNDED,

            'failed',
            'error' => PaymentStatusEnum::FAILED,

            default => PaymentStatusEnum::FAILED,
        };
    }
}
