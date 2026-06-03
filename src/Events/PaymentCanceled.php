<?php

namespace Equidna\StagHerd\Events;

use Equidna\StagHerd\Domain\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a payment is canceled.
 */
class PaymentCanceled
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Creates a new PaymentCanceled event.
     *
     * @param Payment $payment The canceled payment.
     */
    public function __construct(public Payment $payment)
    {
        //
    }
}
