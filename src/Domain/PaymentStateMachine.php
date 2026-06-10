<?php

namespace Equidna\StagHerd\Domain;

use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;
use Equidna\StagHerd\Exceptions\InvalidStateTransitionException;
use InvalidArgumentException;

final class PaymentStateMachine
{
    /**
     * Transiciones generales permitidas entre estados.
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
                PaymentStatusEnum::APPROVED,
                PaymentStatusEnum::REJECTED,
            ],
        ];
    }

    public static function normalize(?string $status): PaymentStatusEnum
    {
        if ($status === null || trim($status) === '') {
            return PaymentStatusEnum::PENDING;
        }

        $normalized = PaymentStatusEnum::tryFrom(strtoupper(trim($status)));

        if ($normalized === null) {
            throw new InvalidArgumentException("Invalid payment status [{$status}].");
        }

        return $normalized;
    }

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

    public static function assertCanTransition(
        PaymentStatusEnum $from,
        PaymentStatusEnum $to,
    ): void {
        if (! self::canTransition($from, $to)) {
            throw InvalidStateTransitionException::fromTo(
                from: $from->value,
                to: $to->value,
            );
        }
    }

    public static function transition(
        Payment $payment,
        PaymentStatusEnum $to,
        ?string $providerStatus = null,
    ): Payment {
        self::assertCanTransition(
            from: $payment->status,
            to: $to,
        );

        return $payment->withStatus(
            status: $to,
            providerStatus: $providerStatus,
        );
    }

    public static function canBeCanceled(PaymentStatusEnum $status): bool
    {
        return $status === PaymentStatusEnum::PENDING;
    }

    public static function assertCanBeCanceled(Payment $payment): void
    {
        if (! self::canBeCanceled($payment->status)) {
            throw InvalidStateTransitionException::fromTo(
                from: $payment->status->value,
                to: PaymentStatusEnum::CANCELED->value,
            );
        }
    }

    public static function canBeRefunded(PaymentStatusEnum $status): bool
    {
        return $status === PaymentStatusEnum::APPROVED;
    }

    public static function assertCanBeRefunded(Payment $payment): void
    {
        if (! self::canBeRefunded($payment->status)) {
            throw InvalidStateTransitionException::fromTo(
                from: $payment->status->value,
                to: PaymentStatusEnum::REFUNDED->value,
            );
        }
    }

    public static function canBeReconciled(PaymentStatusEnum $status): bool
    {
        return in_array($status, [
            PaymentStatusEnum::PENDING,
            PaymentStatusEnum::FAILED,
        ], true);
    }

    public static function assertCanBeReconciled(Payment $payment): void
    {
        if (! self::canBeReconciled($payment->status)) {
            throw new InvalidArgumentException(
                "Payment with status [{$payment->status->value}] cannot be reconciled."
            );
        }
    }

    public static function canReceiveWebhookUpdate(
        PaymentStatusEnum $currentStatus,
        PaymentStatusEnum $incomingStatus,
    ): bool {
        return self::canTransition(
            from: $currentStatus,
            to: $incomingStatus,
        );
    }

    public static function assertCanReceiveWebhookUpdate(
        Payment $payment,
        PaymentStatusEnum $incomingStatus,
    ): void {
        if (! self::canReceiveWebhookUpdate($payment->status, $incomingStatus)) {
            throw InvalidStateTransitionException::fromTo(
                from: $payment->status->value,
                to: $incomingStatus->value,
            );
        }
    }

    public static function canApplyProviderResult(
        Payment $payment,
        PaymentStatusEnum $providerResultStatus,
    ): bool {
        return self::canTransition(
            from: $payment->status,
            to: $providerResultStatus,
        );
    }

    public static function assertCanApplyProviderResult(
        Payment $payment,
        PaymentStatusEnum $providerResultStatus,
    ): void {
        self::assertCanTransition(
            from: $payment->status,
            to: $providerResultStatus,
        );
    }
}
