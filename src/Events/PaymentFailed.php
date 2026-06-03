<?php

namespace Equidna\StagHerd\Events;

use Equidna\StagHerd\Domain\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a payment is failed.
 */
class PaymentFailed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Creates a new PaymentFailed event.
     *
     * @param Payment $payment The failed payment.
     */
    public function __construct(public Payment $payment)
    {
        //
    }
}
