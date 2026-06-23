<?php

namespace Equidna\StagHerd\Events;

use Equidna\StagHerd\Data\WebhookPayloadData;
use Throwable;

final readonly class PaymentWebhookFailed
{
    public function __construct(
        public WebhookPayloadData $webhook,
        public Throwable $exception,
    ) {
        //
    }
}
