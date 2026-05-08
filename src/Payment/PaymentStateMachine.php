<?php

namespace Equidna\StagHerd\Payment;

use Equidna\StagHerd\Enums\PaymentStatus;

final class PaymentStateMachine
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function transitions(): array
    {
        return [
            PaymentStatus::PENDING->value => [
                PaymentStatus::PENDING->value,
                PaymentStatus::APPROVED->value,
                PaymentStatus::REJECTED->value,
                PaymentStatus::DECLINED->value,
                PaymentStatus::CANCELED->value,
                PaymentStatus::REFUNDED->value,
                PaymentStatus::CHARGEBACK->value,
            ],
            PaymentStatus::APPROVED->value => [
                PaymentStatus::APPROVED->value,
                PaymentStatus::REFUNDED->value,
                PaymentStatus::CHARGEBACK->value,
                PaymentStatus::CANCELED->value,
            ],
            PaymentStatus::REJECTED->value => [
                PaymentStatus::REJECTED->value,
            ],
            PaymentStatus::DECLINED->value => [
                PaymentStatus::DECLINED->value,
            ],
            PaymentStatus::CANCELED->value => [
                PaymentStatus::CANCELED->value,
            ],
            PaymentStatus::REFUNDED->value => [
                PaymentStatus::REFUNDED->value,
            ],
            PaymentStatus::CHARGEBACK->value => [
                PaymentStatus::CHARGEBACK->value,
            ],
        ];
    }

    public static function normalize(?string $status): ?PaymentStatus
    {
        if ($status === null || $status === '') {
            return PaymentStatus::PENDING;
        }

        return PaymentStatus::tryFrom(strtoupper($status));
    }

    public static function canTransition(PaymentStatus $from, PaymentStatus $to): bool
    {
        return in_array(
            $to->value,
            self::transitions()[$from->value] ?? [],
            true,
        );
    }
}
