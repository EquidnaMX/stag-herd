<?php

namespace Equidna\StagHerd\Events;

use Equidna\StagHerd\Domain\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a payment changed.
 */
class PaymentStateChanged
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Creates a new PaymentStateChanged event.
     *
     * @param Payment $payment The payment that changed.
     */
    public function __construct(public Payment $payment)
    {
        //
    }
}
