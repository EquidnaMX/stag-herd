<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\WebhookIdempotencyStore;
use Equidna\StagHerd\Contracts\WebhookParser;
use Equidna\StagHerd\Data\NormalizedWebhookData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\WebhookPayloadData;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Events\PaymentWebhookProcessed;
use Equidna\StagHerd\Events\PaymentWebhookReceived;
use Equidna\StagHerd\Exceptions\DuplicateWebhookException;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;
use Throwable;

final readonly class ProcessPaymentWebhook
{
    public function __construct(
        private LookupPayment $lookupPayment,
        private WebhookIdempotencyStore $idempotency,
    ) {
        //
    }

    public function handle(WebhookPayloadData $payload): ?Payment
    {
        $parser = $this->parserFor($payload->provider);
        $webhook = $parser->parse($payload);
        $idempotencyKey = $webhook->idempotencyKey(
            (string) config('stag-herd.webhooks.idempotency.prefix', 'stag-herd:webhooks')
        );
        $ttlSeconds = (int) config('stag-herd.webhooks.idempotency.ttl_seconds', 86400);

        event(new PaymentWebhookReceived($webhook));

        $claimed = $this->idempotency->claim(
            key: $idempotencyKey,
            ttlSeconds: $ttlSeconds,
        );

        if (! $claimed) {
            throw DuplicateWebhookException::withKey($idempotencyKey);
        }

        try {
            $payment = $this->lookupPayment->handle($this->lookupData($payload, $webhook));

            event(new PaymentWebhookProcessed($webhook, $payment));

            $this->idempotency->markProcessed($idempotencyKey, $ttlSeconds);

            return $payment;
        } catch (Throwable $exception) {
            $this->idempotency->releaseIfProcessing($idempotencyKey);

            throw $exception;
        }
    }

    private function lookupData(WebhookPayloadData $payload, NormalizedWebhookData $webhook): PaymentLookupData
    {
        if ($webhook->providerPaymentId) {
            return new PaymentLookupData(
                provider: $payload->provider,
                providerPaymentId: $webhook->providerPaymentId,
            );
        }

        if ($webhook->providerOrderId) {
            return new PaymentLookupData(
                provider: $payload->provider,
                providerOrderId: $webhook->providerOrderId,
            );
        }

        throw UnsupportedOperationException::forOperation(
            'webhook',
            "Webhook resource type [{$webhook->resourceType}] is not supported yet."
        );
    }

    private function parserFor(string $provider): WebhookParser
    {
        $parserClass = config("stag-herd.providers.{$provider}.webhooks.parser");

        if (! $parserClass) {
            throw UnsupportedOperationException::forOperation(
                'webhook',
                "Provider [{$provider}] does not define a webhook parser."
            );
        }

        $parser = app($parserClass);

        if (! $parser instanceof WebhookParser) {
            throw UnsupportedOperationException::forOperation(
                'webhook',
                "Webhook parser [{$parserClass}] must implement [" . WebhookParser::class . '].'
            );
        }

        return $parser;
    }
}
