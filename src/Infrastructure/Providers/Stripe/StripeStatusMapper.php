<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe;

use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;

final class StripeStatusMapper
{
    public function map(?string $status): PaymentStatusEnum
    {
        return match (strtolower((string) $status)) {
            'succeeded' => PaymentStatusEnum::APPROVED,

            'requires_payment_method',
            'requires_confirmation',
            'requires_action',
            'processing',
            'requires_capture' => PaymentStatusEnum::PENDING,

            'canceled' => PaymentStatusEnum::CANCELED,

            'refunded' => PaymentStatusEnum::REFUNDED,

            default => PaymentStatusEnum::FAILED,
        };
    }
}
