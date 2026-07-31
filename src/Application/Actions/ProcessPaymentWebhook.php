<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\BillingResourceRepository;
use Equidna\StagHerd\Contracts\WebhookIdempotencyStore;
use Equidna\StagHerd\Contracts\WebhookParser;
use Equidna\StagHerd\Data\NormalizedWebhookData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\ProviderReferencesData;
use Equidna\StagHerd\Data\WebhookPayloadData;
use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;
use Equidna\StagHerd\Domain\Payment;
use Equidna\StagHerd\Events\CheckoutCompleted;
use Equidna\StagHerd\Events\InvoicePaid;
use Equidna\StagHerd\Events\InvoicePaymentFailed;
use Equidna\StagHerd\Events\PaymentApproved;
use Equidna\StagHerd\Events\PaymentRefunded;
use Equidna\StagHerd\Events\PaymentWebhookProcessed;
use Equidna\StagHerd\Events\PaymentWebhookReceived;
use Equidna\StagHerd\Events\SubscriptionStatusChanged;
use Equidna\StagHerd\Exceptions\DuplicateWebhookException;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;
use Throwable;

final readonly class ProcessPaymentWebhook
{
    public function __construct(
        private LookupPayment $lookupPayment,
        private WebhookIdempotencyStore $idempotency,
        private ?BillingResourceRepository $billingResources = null,
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

        if (!$claimed) {
            throw DuplicateWebhookException::withKey($idempotencyKey);
        }

        try {
            $payment = null;

            if (!$this->isBillingResource($webhook)) {
                $payment = $this->lookupPayment->handle($this->lookupData($payload, $webhook));
            } else {
                if ($this->storeBillingResource($webhook)) {
                    $payment = $this->dispatchBillingEvent($webhook);
                }
            }

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
                method: $webhook->method,
                providerPaymentId: $webhook->providerPaymentId,
            );
        }

        if ($webhook->providerOrderId) {
            return new PaymentLookupData(
                provider: $payload->provider,
                method: $webhook->method,
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

        if (!$parserClass) {
            throw UnsupportedOperationException::forOperation(
                'webhook',
                "Provider [{$provider}] does not define a webhook parser."
            );
        }

        $parser = app($parserClass);

        if (!$parser instanceof WebhookParser) {
            throw UnsupportedOperationException::forOperation(
                'webhook',
                "Webhook parser [{$parserClass}] must implement [" . WebhookParser::class . '].'
            );
        }

        return $parser;
    }

    private function isBillingResource(NormalizedWebhookData $webhook): bool
    {
        if (in_array($webhook->resourceType, ['checkout_session', 'subscription', 'invoice'], true)) {
            return true;
        }

        return in_array($webhook->resourceType, ['payment_intent', 'charge', 'refund'], true)
            && is_string(data_get($webhook->rawPayload, 'data.object.metadata.purchase_uuid'));
    }

    private function storeBillingResource(NormalizedWebhookData $webhook): bool
    {
        if (!$this->billingResources instanceof BillingResourceRepository) {
            return true;
        }

        $created = data_get($webhook->rawPayload, 'created');

        return $this->billingResources->upsert(
            $webhook->provider,
            $webhook->credentialContext,
            in_array($webhook->resourceType, ['payment_intent', 'charge', 'refund'], true)
                ? 'payment'
                : $webhook->resourceType,
            $webhook->resourceId,
            $webhook->status,
            $webhook->rawPayload,
            is_numeric($created) ? (int) $created : null,
        );
    }

    private function dispatchBillingEvent(NormalizedWebhookData $webhook): ?Payment
    {
        if (in_array($webhook->eventType, ['payment_intent.succeeded', 'charge.succeeded'], true)) {
            $payment = $this->billingPayment($webhook, PaymentStatusEnum::APPROVED);
            event(new PaymentApproved($payment));

            return $payment;
        }
        $isSuccessfulRefund = $webhook->eventType === 'charge.refunded'
            || (in_array($webhook->eventType, ['refund.created', 'refund.updated'], true)
                && in_array($webhook->status, ['succeeded', 'completed'], true));
        if ($isSuccessfulRefund) {
            $payment = $this->billingPayment($webhook, PaymentStatusEnum::REFUNDED);
            event(new PaymentRefunded($payment));

            return $payment;
        }

        match ($webhook->eventType) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded' => event(new CheckoutCompleted($webhook)),
            'invoice.paid' => event(new InvoicePaid($webhook)),
            'invoice.payment_failed' => event(new InvoicePaymentFailed($webhook)),
            default => str_starts_with($webhook->eventType, 'customer.subscription.')
                ? event(new SubscriptionStatusChanged($webhook))
                : null,
        };

        return null;
    }

    private function billingPayment(NormalizedWebhookData $webhook, PaymentStatusEnum $status): Payment
    {
        $object = data_get($webhook->rawPayload, 'data.object', []);
        $object = is_array($object) ? $object : [];
        $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];

        return new Payment(
            id: $webhook->providerPaymentId ?: $webhook->resourceId,
            provider: $webhook->provider,
            method: $webhook->method ?: 'hosted_checkout',
            amount: (int) ($object['amount_received'] ?? $object['amount'] ?? 0),
            currency: strtoupper((string) ($object['currency'] ?? '')),
            status: $status,
            providerStatus: $webhook->status,
            externalReference: (string) ($metadata['purchase_uuid'] ?? ''),
            references: new ProviderReferencesData(
                providerPaymentId: $webhook->providerPaymentId ?: $webhook->resourceId,
            ),
            metadata: $metadata,
        );
    }
}
