<?php

/**
 * Event dispatched when a payment link is generated.
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
 * Dispatched when a payment link is generated.
 */
class PaymentLinkGenerated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Creates a new PaymentLinkGenerated event.
     *
     * @param Payment $payment  The payment with generated link.
     */
    public function __construct(public Payment $payment)
    {
        //
    }
}
