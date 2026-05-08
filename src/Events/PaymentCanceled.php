<?php

/**
 * Event dispatched when a payment is canceled.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Events
 *
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Events;

use Equidna\StagHerd\Payment\Payment;
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
