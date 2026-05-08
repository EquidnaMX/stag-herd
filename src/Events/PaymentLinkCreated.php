<?php

/**
 * Event dispatched when an external payment link is created.
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
 * Dispatched when a payment link is generated.
 */
class PaymentLinkCreated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Creates a new PaymentLinkCreated event.
     *
     * @param Payment $payment The payment instance.
     * @param string  $link    External payment link.
     */
    public function __construct(
        public Payment $payment,
        public string $link,
    ) {
        //
    }
}
