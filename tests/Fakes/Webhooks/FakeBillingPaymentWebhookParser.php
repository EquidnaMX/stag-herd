<?php

namespace Equidna\StagHerd\Tests\Fakes\Webhooks;

use Equidna\StagHerd\Data\NormalizedWebhookData;
use Equidna\StagHerd\Contracts\WebhookParser;
use Equidna\StagHerd\Data\WebhookPayloadData;

final class FakeBillingPaymentWebhookParser implements WebhookParser
{
    public function parse(WebhookPayloadData $webhook): NormalizedWebhookData
    {
        return new NormalizedWebhookData(
            provider: 'stripe',
            eventType: 'payment_intent.succeeded',
            resourceType: 'payment_intent',
            resourceId: 'pi_123',
            providerPaymentId: 'pi_123',
            method: 'card',
            rawPayload: [
                'created' => 200,
                'data' => ['object' => [
                    'id' => 'pi_123',
                    'amount_received' => 12500,
                    'currency' => 'mxn',
                    'metadata' => [
                        'purchase_uuid' => 'purchase-uuid',
                        'payment_method_uuid' => 'method-uuid',
                    ],
                ]],
            ],
            providerEventId: 'evt_1',
            credentialContext: $webhook->credentialContext,
            status: 'succeeded',
        );
    }
}
