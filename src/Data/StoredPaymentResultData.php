<?php

namespace Equidna\StagHerd\Data;

use Equidna\StagHerd\Domain\Payment;

final readonly class StoredPaymentResultData
{
    public function __construct(
        public Payment $payment,
        public ?PaymentMethodData $paymentMethod = null,
    ) {
        //
    }
}
