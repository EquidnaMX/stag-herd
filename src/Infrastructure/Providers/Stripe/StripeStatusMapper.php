<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe;

use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;
use Illuminate\Support\Arr;

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

    /**
     * @param array<string, mixed> $paymentIntent
     */
    public function mapPaymentIntent(array $paymentIntent): PaymentStatusEnum
    {
        $status = strtolower((string) Arr::get($paymentIntent, 'status'));
        $hasLastPaymentError = Arr::get($paymentIntent, 'last_payment_error') !== null;

        return match ($status) {
            'succeeded' => PaymentStatusEnum::APPROVED,

            'requires_payment_method' => $hasLastPaymentError
                ? PaymentStatusEnum::FAILED
                : PaymentStatusEnum::PENDING,

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
