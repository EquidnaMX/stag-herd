<?php

namespace Equidna\StagHerd\Infrastructure\Providers\PayPal;

use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;

class PayPalStatusMapper
{
    public function map(?string $status): PaymentStatusEnum
    {
        return match (strtoupper((string) $status)) {
            'CREATED',
            'SAVED',
            'APPROVED',
            'PAYER_ACTION_REQUIRED' => PaymentStatusEnum::PENDING,

            'COMPLETED',
            'CAPTURED' => PaymentStatusEnum::APPROVED,

            'VOIDED',
            'CANCELLED',
            'CANCELED' => PaymentStatusEnum::CANCELED,

            'REFUNDED',
            'PARTIALLY_REFUNDED' => PaymentStatusEnum::REFUNDED,

            'DECLINED',
            'DENIED',
            'FAILED',
            'EXPIRED' => PaymentStatusEnum::FAILED,

            default => PaymentStatusEnum::PENDING,
        };
    }
}
