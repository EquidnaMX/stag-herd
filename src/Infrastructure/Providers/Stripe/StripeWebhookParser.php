<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe;

use Equidna\StagHerd\Contracts\WebhookParser;
use Equidna\StagHerd\Data\NormalizedWebhookData;
use Equidna\StagHerd\Data\WebhookPayloadData;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Equidna\StagHerd\Exceptions\InvalidWebhookSignatureException;
use Illuminate\Support\Arr;

final class StripeWebhookParser implements WebhookParser
{
    public function parse(WebhookPayloadData $webhook): NormalizedWebhookData
    {
        $this->verifySignature($webhook);

        $payload = $webhook->payload;

        $eventId = (string) Arr::get($payload, 'id', 'unknown');
        $eventType = (string) Arr::get($payload, 'type', 'unknown');
        $object = Arr::get($payload, 'data.object', []);

        if (! is_array($object)) {
            throw InvalidPaymentPayloadException::invalidField(
                'data.object',
                'Stripe webhook data.object must be an object.'
            );
        }

        $resourceType = (string) Arr::get($object, 'object', 'event');

        return new NormalizedWebhookData(
            provider: 'stripe',
            eventType: $eventType,
            resourceType: $resourceType,
            resourceId: $eventId,
            providerPaymentId: $this->resolvePaymentIntentId($resourceType, $object),
            providerOrderId: $this->nullableString(Arr::get($object, 'metadata.external_reference')),
            rawPayload: $payload,
        );
    }

    /**
     * @param array<string, mixed> $object
     */
    private function resolvePaymentIntentId(string $resourceType, array $object): ?string
    {
        if ($resourceType === 'payment_intent') {
            return $this->nullableString(Arr::get($object, 'id'));
        }

        if ($resourceType === 'charge') {
            return $this->nullableString(Arr::get($object, 'payment_intent'));
        }

        if ($resourceType === 'refund') {
            return $this->nullableString(Arr::get($object, 'payment_intent'));
        }

        return null;
    }

    private function verifySignature(WebhookPayloadData $webhook): void
    {
        $secret = config('stag-herd.providers.stripe.credentials.webhook_secret');

        /*
         * Si no configuras STRIPE_WEBHOOK_SECRET, no valida firma.
         * Para producción sí deberías configurarlo.
         */
        if (! $secret) {
            return;
        }

        $signatureHeader = $this->header($webhook, 'stripe-signature');
        $timestamp = $this->signaturePart($signatureHeader, 't');
        $expectedSignatures = $this->signatureParts($signatureHeader, 'v1');

        if (! $timestamp || $expectedSignatures === []) {
            throw InvalidWebhookSignatureException::forProvider('stripe');
        }

        $tolerance = (int) config('stag-herd.providers.stripe.webhooks.tolerance_seconds', 300);

        if ($tolerance > 0 && abs(time() - (int) $timestamp) > $tolerance) {
            throw InvalidWebhookSignatureException::forProvider('stripe');
        }

        $signedPayload = $timestamp . '.' . $webhook->rawBody;
        $computedSignature = hash_hmac('sha256', $signedPayload, (string) $secret);

        foreach ($expectedSignatures as $signature) {
            if (hash_equals($computedSignature, $signature)) {
                return;
            }
        }

        throw InvalidWebhookSignatureException::forProvider('stripe');
    }

    private function header(WebhookPayloadData $webhook, string $name): string
    {
        $needle = strtolower($name);

        foreach ($webhook->headers as $key => $value) {
            if (strtolower((string) $key) !== $needle) {
                continue;
            }

            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        throw InvalidWebhookSignatureException::forProvider('stripe');
    }

    private function signaturePart(string $header, string $key): ?string
    {
        return $this->signatureParts($header, $key)[0] ?? null;
    }

    /**
     * @return list<string>
     */
    private function signatureParts(string $header, string $key): array
    {
        $values = [];

        foreach (explode(',', $header) as $part) {
            [$partKey, $partValue] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($partKey === $key && $partValue !== null && $partValue !== '') {
                $values[] = $partValue;
            }
        }

        return $values;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
