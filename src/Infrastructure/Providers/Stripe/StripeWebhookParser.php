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
            providerOrderId: $this->resolveProviderOrderId($object),
            method: $this->resolveMethod($object),
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

    /**
     * @param array<string, mixed> $object
     */
    private function resolveProviderOrderId(array $object): ?string
    {
        return $this->nullableString(
            Arr::get($object, 'metadata.external_reference')
                ?? Arr::get($object, 'charges.data.0.metadata.external_reference')
        );
    }

    /**
     * @param array<string, mixed> $object
     */
    private function resolveMethod(array $object): ?string
    {
        $declaredFamily = $this->normalizeMethod(
            Arr::get($object, 'metadata.payment_method_family')
        );

        if ($declaredFamily === 'spei') {
            return 'spei';
        }

        $bankTransferType = $this->nullableString(
            Arr::get($object, 'payment_method_options.customer_balance.bank_transfer.type')
                ?? Arr::get($object, 'next_action.display_bank_transfer_instructions.type')
                ?? Arr::get($object, 'payment_method_details.customer_balance.bank_transfer.type')
                ?? Arr::get($object, 'charges.data.0.payment_method_details.customer_balance.bank_transfer.type')
        );

        if ($bankTransferType === 'mx_bank_transfer') {
            return 'spei';
        }

        $paymentMethodDetailsType = $this->normalizeMethod(
            Arr::get($object, 'payment_method_details.type')
                ?? Arr::get($object, 'charges.data.0.payment_method_details.type')
        );

        if ($paymentMethodDetailsType === 'card') {
            return $declaredFamily ?? 'card';
        }

        if ($paymentMethodDetailsType === 'customer_balance') {
            return $bankTransferType === 'mx_bank_transfer' ? 'spei' : null;
        }

        $paymentMethodTypes = Arr::get($object, 'payment_method_types', []);

        if (is_array($paymentMethodTypes)) {
            $normalizedTypes = array_values(array_filter(array_map(
                fn(mixed $type): ?string => $this->normalizeMethod($type),
                $paymentMethodTypes,
            )));

            if (in_array('customer_balance', $normalizedTypes, true) && $bankTransferType === 'mx_bank_transfer') {
                return 'spei';
            }

            if (in_array('card', $normalizedTypes, true)) {
                return $declaredFamily ?? 'card';
            }
        }

        return $declaredFamily;
    }

    private function verifySignature(WebhookPayloadData $webhook): void
    {
        $secret = config('stag-herd.providers.stripe.credentials.webhook_secret');

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

    private function normalizeMethod(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return strtolower(trim($value));
    }
}
