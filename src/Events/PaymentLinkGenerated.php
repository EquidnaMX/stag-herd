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

class PaymentLinkGenerated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Payment $payment)
    {
        //
    }
}
