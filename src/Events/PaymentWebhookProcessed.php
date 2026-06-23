<?php

namespace Equidna\StagHerd\Events;

use Equidna\StagHerd\Data\NormalizedWebhookData;
use Equidna\StagHerd\Domain\Payment;

final readonly class PaymentWebhookProcessed
{
    public function __construct(
        public NormalizedWebhookData $webhook,
        public Payment $payment,
    ) {
        //
    }
}
