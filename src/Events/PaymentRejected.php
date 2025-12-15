<?php

/**
 * Event dispatched when a payment is rejected.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Events
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Events;

use Equidna\StagHerd\Payment\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a payment is rejected.
 */
class PaymentRejected
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Creates a new PaymentRejected event.
     *
     * @param Payment $payment  The rejected payment.
     */
    public function __construct(public Payment $payment)
    {
        //
    }
}
