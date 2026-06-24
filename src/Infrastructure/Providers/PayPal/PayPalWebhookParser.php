<?php

namespace Equidna\StagHerd\Infrastructure\Providers\PayPal;

use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Contracts\WebhookParser;
use Equidna\StagHerd\Data\NormalizedWebhookData;
use Equidna\StagHerd\Data\WebhookPayloadData;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Equidna\StagHerd\Exceptions\InvalidWebhookSignatureException;
use Equidna\StagHerd\Exceptions\ProviderNotConfiguredException;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;

final readonly class PayPalWebhookParser implements WebhookParser
{
    private const SUPPORTED_EVENT = 'PAYMENT.CAPTURE.COMPLETED';

    public function __construct(
        private PayPalGateway $gateway,
    ) {
        //
    }

    public function parse(WebhookPayloadData $webhook): NormalizedWebhookData
    {
        $this->verifySignature($webhook);

        $eventType = $this->eventType($webhook);

        if ($eventType !== self::SUPPORTED_EVENT) {
            throw UnsupportedOperationException::forOperation(
                'webhook',
                sprintf(
                    'PayPal webhook event [%s] is not supported. Only [%s] is currently supported.',
                    $eventType,
                    self::SUPPORTED_EVENT,
                ),
            );
        }

        $providerPaymentId = $this->providerPaymentId($webhook);
        $providerOrderId = $this->providerOrderId($webhook);

        return new NormalizedWebhookData(
            provider: 'paypal',
            eventType: $eventType,
            resourceType: $this->resourceType($webhook),
            resourceId: $providerPaymentId,
            providerPaymentId: $providerPaymentId,
            providerOrderId: $providerOrderId,
            rawPayload: $webhook->payload,
        );
    }

    private function verifySignature(WebhookPayloadData $webhook): void
    {
        $webhookId = config('stag-herd.providers.paypal.credentials.webhook_id');

        if (! $webhookId) {
            throw ProviderNotConfiguredException::missingCredential('paypal', 'webhook_id');
        }

        $verificationPayload = [
            'auth_algo' => $this->requiredHeader($webhook, 'paypal-auth-algo'),
            'cert_url' => $this->requiredHeader($webhook, 'paypal-cert-url'),
            'transmission_id' => $this->requiredHeader($webhook, 'paypal-transmission-id'),
            'transmission_sig' => $this->requiredHeader($webhook, 'paypal-transmission-sig'),
            'transmission_time' => $this->requiredHeader($webhook, 'paypal-transmission-time'),
            'webhook_id' => (string) $webhookId,
            'webhook_event' => $webhook->payload,
        ];

        if (! $this->gateway->verifyWebhookSignature($verificationPayload)) {
            throw InvalidWebhookSignatureException::forProvider('paypal');
        }
    }

    private function eventType(WebhookPayloadData $webhook): string
    {
        $eventType = data_get($webhook->payload, 'event_type');

        if (! $eventType) {
            throw InvalidPaymentPayloadException::missingField('event_type');
        }

        return strtoupper((string) $eventType);
    }

    private function resourceType(WebhookPayloadData $webhook): string
    {
        $resourceType = data_get($webhook->payload, 'resource_type');

        if ($resourceType === null || $resourceType === '') {
            return 'capture';
        }

        return strtolower((string) $resourceType);
    }

    private function providerPaymentId(WebhookPayloadData $webhook): string
    {
        $captureId = data_get($webhook->payload, 'resource.id');

        if (! $captureId) {
            throw InvalidPaymentPayloadException::missingField('resource.id');
        }

        return (string) $captureId;
    }

    private function providerOrderId(WebhookPayloadData $webhook): ?string
    {
        $orderId = data_get($webhook->payload, 'resource.supplementary_data.related_ids.order_id')
            ?? data_get($webhook->payload, 'resource.related_ids.order_id')
            ?? data_get($webhook->payload, 'resource.order_id');

        if ($orderId === null || $orderId === '') {
            return null;
        }

        return (string) $orderId;
    }

    private function requiredHeader(WebhookPayloadData $webhook, string $name): string
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

        throw InvalidWebhookSignatureException::forProvider('paypal');
    }
}
