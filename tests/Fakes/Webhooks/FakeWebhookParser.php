<?php

namespace Equidna\StagHerd\Tests\Fakes\Webhooks;

use Equidna\StagHerd\Data\NormalizedWebhookData;
use Equidna\StagHerd\Contracts\WebhookParser;
use Equidna\StagHerd\Data\WebhookPayloadData;

final class FakeWebhookParser implements WebhookParser
{
    public function parse(WebhookPayloadData $webhook): NormalizedWebhookData
    {
        return new NormalizedWebhookData(
            provider: 'mercado_pago',
            eventType: 'payment.updated',
            resourceType: 'payment',
            resourceId: '123',
            providerPaymentId: '123',
            rawPayload: $webhook->payload,
        );
    }
}
