<?php

namespace Equidna\StagHerd\Contracts;

use Equidna\StagHerd\Data\NormalizedWebhookData;
use Equidna\StagHerd\Data\WebhookPayloadData;

interface WebhookParser
{
    public function parse(WebhookPayloadData $webhook): NormalizedWebhookData;
}
