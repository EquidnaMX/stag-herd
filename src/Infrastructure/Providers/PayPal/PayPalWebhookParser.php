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
    public function __construct(
        private PayPalGateway $gateway,
    ) {
        //
    }

    public function parse(WebhookPayloadData $webhook): NormalizedWebhookData
    {
        $this->verifySignature($webhook);

        $eventType = $this->eventType($webhook);
        $mapping = $this->mapping($eventType, $webhook);

        return new NormalizedWebhookData(
            provider: 'paypal',
            eventType: $mapping['event_type'],
            resourceType: $mapping['resource_type'],
            resourceId: $mapping['resource_id'],
            providerPaymentId: $mapping['provider_payment_id'],
            providerOrderId: $mapping['provider_order_id'],
            rawPayload: $webhook->payload,
            providerEventId: (string) data_get($webhook->payload, 'id'),
            credentialContext: $webhook->credentialContext,
            status: $mapping['status'],
            customerId: $mapping['customer_id'],
            subscriptionId: $mapping['subscription_id'],
            invoiceId: $mapping['invoice_id'],
            paymentStatus: $mapping['payment_status'],
        );
    }

    private function verifySignature(WebhookPayloadData $webhook): void
    {
        $webhookId = config('stag-herd.providers.paypal.credentials.webhook_id');

        if (!$webhookId) {
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

        if (!$this->gateway->verifyWebhookSignature($verificationPayload)) {
            throw InvalidWebhookSignatureException::forProvider('paypal');
        }
    }

    private function eventType(WebhookPayloadData $webhook): string
    {
        $eventType = data_get($webhook->payload, 'event_type');

        if (!$eventType) {
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

    /**
     * @return array{
     *   event_type: string,
     *   resource_type: string,
     *   resource_id: string,
     *   provider_payment_id: ?string,
     *   provider_order_id: ?string,
     *   status: ?string,
     *   customer_id: ?string,
     *   subscription_id: ?string,
     *   invoice_id: ?string,
     *   payment_status: ?string
     * }
     */
    private function mapping(string $eventType, WebhookPayloadData $webhook): array
    {
        return match ($eventType) {
            'PAYMENT.CAPTURE.COMPLETED' => [
                'event_type' => $eventType,
                'resource_type' => $this->resourceType($webhook),
                'resource_id' => $this->providerPaymentId($webhook),
                'provider_payment_id' => $this->providerPaymentId($webhook),
                'provider_order_id' => $this->providerOrderId($webhook),
                'status' => $this->nullableString(data_get($webhook->payload, 'resource.status')),
                'customer_id' => null,
                'subscription_id' => null,
                'invoice_id' => null,
                'payment_status' => $this->nullableString(data_get($webhook->payload, 'resource.status')),
            ],
            'PAYMENT.SALE.COMPLETED' => $this->invoiceMapping($webhook, 'invoice.paid'),
            'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => $this->invoiceMapping($webhook, 'invoice.payment_failed'),
            'BILLING.SUBSCRIPTION.CREATED' => $this->subscriptionMapping($webhook, 'customer.subscription.created'),
            'BILLING.SUBSCRIPTION.ACTIVATED',
            'BILLING.SUBSCRIPTION.UPDATED',
            'BILLING.SUBSCRIPTION.SUSPENDED' => $this->subscriptionMapping($webhook, 'customer.subscription.updated'),
            'BILLING.SUBSCRIPTION.CANCELLED',
            'BILLING.SUBSCRIPTION.EXPIRED' => $this->subscriptionMapping($webhook, 'customer.subscription.deleted'),
            default => throw UnsupportedOperationException::forOperation(
                'webhook',
                sprintf('PayPal webhook event [%s] is not supported.', $eventType),
            ),
        };
    }

    /**
     * @return array{
     *   event_type: string,
     *   resource_type: string,
     *   resource_id: string,
     *   provider_payment_id: ?string,
     *   provider_order_id: ?string,
     *   status: ?string,
     *   customer_id: ?string,
     *   subscription_id: ?string,
     *   invoice_id: ?string,
     *   payment_status: ?string
     * }
     */
    private function subscriptionMapping(WebhookPayloadData $webhook, string $normalizedEventType): array
    {
        $subscriptionId = $this->subscriptionId($webhook);
        $status = $this->nullableString(data_get($webhook->payload, 'resource.status'));

        if ($subscriptionId === null) {
            throw InvalidPaymentPayloadException::missingField('resource.id');
        }

        return [
            'event_type' => $normalizedEventType,
            'resource_type' => 'subscription',
            'resource_id' => $subscriptionId,
            'provider_payment_id' => null,
            'provider_order_id' => null,
            'status' => $status,
            'customer_id' => $this->customerId($webhook),
            'subscription_id' => $subscriptionId,
            'invoice_id' => null,
            'payment_status' => null,
        ];
    }

    /**
     * @return array{
     *   event_type: string,
     *   resource_type: string,
     *   resource_id: string,
     *   provider_payment_id: ?string,
     *   provider_order_id: ?string,
     *   status: ?string,
     *   customer_id: ?string,
     *   subscription_id: ?string,
     *   invoice_id: ?string,
     *   payment_status: ?string
     * }
     */
    private function invoiceMapping(WebhookPayloadData $webhook, string $normalizedEventType): array
    {
        $invoiceId = $this->nullableString(data_get($webhook->payload, 'resource.id'))
            ?? $this->subscriptionId($webhook);
        $paymentStatus = $this->nullableString(data_get($webhook->payload, 'resource.status'));

        if ($invoiceId === null) {
            throw InvalidPaymentPayloadException::missingField('resource.id');
        }

        return [
            'event_type' => $normalizedEventType,
            'resource_type' => 'invoice',
            'resource_id' => $invoiceId,
            'provider_payment_id' => $this->nullableString(data_get($webhook->payload, 'resource.id')),
            'provider_order_id' => null,
            'status' => $paymentStatus,
            'customer_id' => $this->customerId($webhook),
            'subscription_id' => $this->subscriptionId($webhook),
            'invoice_id' => $invoiceId,
            'payment_status' => $paymentStatus,
        ];
    }

    private function providerPaymentId(WebhookPayloadData $webhook): string
    {
        $captureId = data_get($webhook->payload, 'resource.id');

        if (!$captureId) {
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

    private function subscriptionId(WebhookPayloadData $webhook): ?string
    {
        $subscriptionId = data_get($webhook->payload, 'resource.billing_agreement_id')
            ?? data_get($webhook->payload, 'resource.supplementary_data.related_ids.subscription_id')
            ?? data_get($webhook->payload, 'resource.id');

        if (!is_string($subscriptionId) || $subscriptionId === '') {
            $subscriptionId = null;
        }

        return $this->nullableString($subscriptionId);
    }

    private function customerId(WebhookPayloadData $webhook): ?string
    {
        return $this->nullableString(
            data_get($webhook->payload, 'resource.subscriber.payer_id')
                ?? data_get($webhook->payload, 'resource.subscriber.email_address')
                ?? data_get($webhook->payload, 'resource.payer.payer_id')
                ?? data_get($webhook->payload, 'resource.payer.email_address')
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
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
