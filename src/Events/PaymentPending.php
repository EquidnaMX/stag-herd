<?php

namespace Equidna\StagHerd\Events;

use Equidna\StagHerd\Domain\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a payment is pending.
 */
class PaymentPending
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Creates a new PaymentPending event.
     *
     * @param Payment $payment The pending payment.
     */
    public function __construct(public Payment $payment)
    {
        //
    }
}
