<?php

namespace Equidna\StagHerd\Domain;

use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;
use InvalidArgumentException;

final class PaymentStateMachine
{
    /**
     * Get the allowed payment status transitions.
     *
     * @return array<string, array<int, PaymentStatusEnum>>
     */
    public static function transitions(): array
    {
        return [
            PaymentStatusEnum::PENDING->value => [
                PaymentStatusEnum::PENDING,
                PaymentStatusEnum::APPROVED,
                PaymentStatusEnum::REJECTED,
                PaymentStatusEnum::CANCELED,
                PaymentStatusEnum::FAILED,
            ],

            PaymentStatusEnum::APPROVED->value => [
                PaymentStatusEnum::APPROVED,
                PaymentStatusEnum::REFUNDED,
            ],

            PaymentStatusEnum::REJECTED->value => [
                PaymentStatusEnum::REJECTED,
            ],

            PaymentStatusEnum::CANCELED->value => [
                PaymentStatusEnum::CANCELED,
            ],

            PaymentStatusEnum::REFUNDED->value => [
                PaymentStatusEnum::REFUNDED,
            ],

            PaymentStatusEnum::FAILED->value => [
                PaymentStatusEnum::FAILED,
                PaymentStatusEnum::PENDING,
            ],
        ];
    }

    /**
     * Convert a string status into a payment status enum.
     */
    public static function normalize(?string $status): PaymentStatusEnum
    {
        if ($status === null || trim($status) === '') {
            return PaymentStatusEnum::PENDING;
        }

        $normalized = PaymentStatusEnum::tryFrom(strtoupper($status));

        if ($normalized === null) {
            throw new InvalidArgumentException("Invalid payment status [{$status}].");
        }

        return $normalized;
    }

    /**
     * Check if a status transition is allowed.
     */
    public static function canTransition(
        PaymentStatusEnum $from,
        PaymentStatusEnum $to,
    ): bool {
        return in_array(
            $to,
            self::transitions()[$from->value] ?? [],
            true,
        );
    }

    /**
     * Apply a valid status transition to a payment.
     */
    public static function transition(
        Payment $payment,
        PaymentStatusEnum $to,
        ?string $providerStatus = null,
    ): Payment {
        if (! self::canTransition($payment->status, $to)) {
            throw new InvalidArgumentException(
                "Cannot transition payment from [{$payment->status->value}] to [{$to->value}]."
            );
        }

        return $payment->withStatus(
            status: $to,
            providerStatus: $providerStatus,
        );
    }
}
