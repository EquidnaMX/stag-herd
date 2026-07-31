<?php

namespace Equidna\StagHerd\Events;

use Equidna\StagHerd\Data\NormalizedWebhookData;

final readonly class CheckoutCompleted
{
    public function __construct(
        public NormalizedWebhookData $webhook,
    ) {
        //
    }
}
