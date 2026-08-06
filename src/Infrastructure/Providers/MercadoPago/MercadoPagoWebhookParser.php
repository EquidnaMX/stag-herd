<?php

namespace Equidna\StagHerd\Infrastructure\Providers\MercadoPago;

use Equidna\StagHerd\Contracts\WebhookParser;
use Equidna\StagHerd\Data\NormalizedWebhookData;
use Equidna\StagHerd\Data\WebhookPayloadData;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Equidna\StagHerd\Exceptions\InvalidWebhookSignatureException;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;

final class MercadoPagoWebhookParser implements WebhookParser
{
    public function parse(WebhookPayloadData $webhook): NormalizedWebhookData
    {
        $this->verifySignature($webhook);

        $providerType = $this->providerType($webhook);

        if ($providerType === 'subscription_preapproval') {
            return $this->subscriptionWebhook($webhook);
        }

        $resourceType = $this->resourceType($webhook);
        $resourceId = $this->resourceId($webhook);
        $eventType = $this->eventType($webhook, $resourceType);

        return new NormalizedWebhookData(
            provider: 'mercado_pago',
            eventType: $eventType,
            resourceType: $resourceType,
            resourceId: $resourceId,
            providerPaymentId: $resourceType === 'payment' ? $resourceId : null,
            providerOrderId: in_array($resourceType, ['order', 'orders', 'merchant_order'], true) ? $resourceId : null,
            rawPayload: $webhook->payload,
            providerEventId: (string) (data_get($webhook->payload, 'id') ?? $resourceId),
            credentialContext: $webhook->credentialContext,
        );
    }

    private function subscriptionWebhook(WebhookPayloadData $webhook): NormalizedWebhookData
    {
        $subscriptionId = $this->resourceId($webhook);
        $action = strtolower((string) (
            data_get($webhook->payload, 'action')
            ?? $this->queryValue($webhook, 'action')
            ?? 'subscription.updated'
        ));

        $eventType = match (true) {
            str_contains($action, 'created') => 'customer.subscription.created',
            str_contains($action, 'cancel') => 'customer.subscription.deleted',
            str_contains($action, 'paused') => 'customer.subscription.updated',
            str_contains($action, 'resumed') => 'customer.subscription.updated',
            default => 'customer.subscription.updated',
        };

        return new NormalizedWebhookData(
            provider: 'mercado_pago',
            eventType: $eventType,
            resourceType: 'subscription',
            resourceId: $subscriptionId,
            providerPaymentId: null,
            providerOrderId: null,
            rawPayload: $webhook->payload,
            providerEventId: (string) (data_get($webhook->payload, 'id') ?? $subscriptionId),
            credentialContext: $webhook->credentialContext,
            subscriptionId: $subscriptionId,
            status: $this->nullableString(
                data_get($webhook->payload, 'data.status')
                    ?? data_get($webhook->payload, 'status')
            ),
        );
    }

    private function verifySignature(WebhookPayloadData $webhook): void
    {
        $secret = config('stag-herd.providers.mercado_pago.credentials.webhook_secret');

        if (!$secret) {
            throw InvalidWebhookSignatureException::forProvider('mercado_pago');
        }

        $signature = $this->header($webhook, 'x-signature');
        $requestId = $this->header($webhook, 'x-request-id');
        $dataId = $this->queryValue($webhook, 'data.id')
            ?? $this->queryValue($webhook, 'data_id')
            ?? data_get($webhook->payload, 'data.id');

        if (!$signature || !$requestId || !$dataId) {
            throw InvalidWebhookSignatureException::forProvider('mercado_pago');
        }

        $parts = $this->signatureParts($signature);
        $timestamp = $parts['ts'] ?? null;
        $received = $parts['v1'] ?? null;

        if (!$timestamp || !$received) {
            throw InvalidWebhookSignatureException::forProvider('mercado_pago');
        }

        $manifest = sprintf(
            'id:%s;request-id:%s;ts:%s;',
            $dataId,
            $requestId,
            $timestamp,
        );

        $expected = hash_hmac('sha256', $manifest, (string) $secret);

        if (!hash_equals($expected, $received)) {
            throw InvalidWebhookSignatureException::forProvider('mercado_pago');
        }
    }

    private function providerType(WebhookPayloadData $webhook): string
    {
        $type = data_get($webhook->payload, 'type')
            ?? data_get($webhook->payload, 'topic')
            ?? $this->queryValue($webhook, 'type')
            ?? $this->queryValue($webhook, 'topic');

        if (!$type) {
            throw InvalidPaymentPayloadException::missingField('type');
        }

        return strtolower((string) $type);
    }

    private function resourceType(WebhookPayloadData $webhook): string
    {
        return $this->providerType($webhook);
    }

    private function resourceId(WebhookPayloadData $webhook): string
    {
        $id = data_get($webhook->payload, 'data.id')
            ?? $this->queryValue($webhook, 'data.id')
            ?? $this->queryValue($webhook, 'data_id')
            ?? data_get($webhook->payload, 'id');

        if (!$id) {
            throw InvalidPaymentPayloadException::missingField('data.id');
        }

        return (string) $id;
    }

    private function eventType(WebhookPayloadData $webhook, string $resourceType): string
    {
        return (string) (
            data_get($webhook->payload, 'action')
            ?? $this->queryValue($webhook, 'action')
            ?? $resourceType . '.updated'
        );
    }

    private function header(WebhookPayloadData $webhook, string $name): ?string
    {
        $needle = strtolower($name);

        foreach ($webhook->headers as $key => $value) {
            if (strtolower((string) $key) !== $needle) {
                continue;
            }

            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            return $value !== null && $value !== '' ? (string) $value : null;
        }

        return null;
    }

    private function queryValue(WebhookPayloadData $webhook, string $key): ?string
    {
        $value = $webhook->query[$key] ?? null;

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    /**
     * @return array<string, string>
     */
    private function signatureParts(string $signature): array
    {
        $parts = [];

        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key && $value) {
                $parts[$key] = $value;
            }
        }

        return $parts;
    }
}
