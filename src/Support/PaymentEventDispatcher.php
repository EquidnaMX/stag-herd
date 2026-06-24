<?php

namespace Equidna\StagHerd\Support;

use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Events\PaymentApproved;
use Equidna\StagHerd\Events\PaymentCanceled;
use Equidna\StagHerd\Events\PaymentFailed;
use Equidna\StagHerd\Events\PaymentPending;
use Equidna\StagHerd\Events\PaymentRefunded;
use Equidna\StagHerd\Events\PaymentRejected;
use Equidna\StagHerd\Events\PaymentStateChanged;

final class PaymentEventDispatcher
{
    public static function dispatchForPayment(
        Payment $payment,
        ?Payment $previousPayment = null,
    ): void {
        if (
            $previousPayment !== null
            && $previousPayment->status === $payment->status
        ) {
            return;
        }

        event(new PaymentStateChanged($payment));

        match ($payment->status) {
            PaymentStatusEnum::PENDING => event(new PaymentPending($payment)),
            PaymentStatusEnum::APPROVED => event(new PaymentApproved($payment)),
            PaymentStatusEnum::REJECTED => event(new PaymentRejected($payment)),
            PaymentStatusEnum::CANCELED => event(new PaymentCanceled($payment)),
            PaymentStatusEnum::REFUNDED => event(new PaymentRefunded($payment)),
            PaymentStatusEnum::FAILED => event(new PaymentFailed($payment)),
        };
    }
}
