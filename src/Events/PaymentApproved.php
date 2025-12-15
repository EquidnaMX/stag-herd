<?php

/**
 * Event dispatched when a payment is approved.
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
 * Dispatched when a payment is approved.
 */
class PaymentApproved
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Creates a new PaymentApproved event.
     *
     * @param Payment $payment  The approved payment.
     */
    public function __construct(public Payment $payment)
    {
        //
    }
}
