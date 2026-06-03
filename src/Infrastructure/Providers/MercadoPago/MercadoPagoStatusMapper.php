<?php

namespace Equidna\StagHerd\Infrastructure\Providers\MercadoPago;

use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;

class MercadoPagoStatusMapper
{
    public function map(?string $status): PaymentStatusEnum
    {
        return match (strtolower((string) $status)) {
            'approved',
            'accredited' => PaymentStatusEnum::APPROVED,

            'pending',
            'in_process',
            'in_mediation',
            'authorized' => PaymentStatusEnum::PENDING,

            'rejected' => PaymentStatusEnum::REJECTED,

            'cancelled',
            'canceled' => PaymentStatusEnum::CANCELED,

            'refunded' => PaymentStatusEnum::REFUNDED,

            'charged_back',
            'failed',
            'error' => PaymentStatusEnum::FAILED,

            default => PaymentStatusEnum::FAILED,
        };
    }
}
