<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe;

use Carbon\CarbonImmutable;
use Equidna\StagHerd\Contracts\BillingCatalogProvider;
use Equidna\StagHerd\Contracts\BillingProvider;
use Equidna\StagHerd\Contracts\CreatesCustomerPortal;
use Equidna\StagHerd\Contracts\Gateways\StripeGateway;
use Equidna\StagHerd\Contracts\HostedCheckoutProvider;
use Equidna\StagHerd\Contracts\ManagesSubscriptions;
use Equidna\StagHerd\Data\BillingPortalRequestData;
use Equidna\StagHerd\Data\BillingPortalSessionData;
use Equidna\StagHerd\Data\BillingPriceData;
use Equidna\StagHerd\Data\BillingProductData;
use Equidna\StagHerd\Data\CheckoutLookupData;
use Equidna\StagHerd\Data\CheckoutRequestData;
use Equidna\StagHerd\Data\CheckoutSessionData;
use Equidna\StagHerd\Data\SubscriptionCancellationData;
use Equidna\StagHerd\Data\SubscriptionData;
use Equidna\StagHerd\Data\SubscriptionLookupData;
use Equidna\StagHerd\Domain\Enums\CheckoutMode;
use Equidna\StagHerd\Domain\Enums\CheckoutStatusEnum;
use Equidna\StagHerd\Domain\Enums\SubscriptionStatusEnum;
use Illuminate\Support\Arr;

final readonly class StripeBillingProvider implements
    BillingProvider,
    HostedCheckoutProvider,
    ManagesSubscriptions,
    CreatesCustomerPortal,
    BillingCatalogProvider
{
    public function __construct(
        private StripeGateway $gateway,
    ) {
        //
    }

    public function getName(): string
    {
        return 'stripe';
    }

    public function createCheckout(CheckoutRequestData $request): CheckoutSessionData
    {
        $payload = [
            'mode' => $request->mode->value,
            'line_items' => array_map(
                static fn ($item): array => $item->toArray(),
                $request->lineItems,
            ),
            'success_url' => $request->successUrl,
            'cancel_url' => $request->cancelUrl,
            'client_reference_id' => $request->externalReference,
            'metadata' => $request->metadata,
        ];

        if ($request->customerId) {
            $payload['customer'] = $request->customerId;
        } elseif ($request->customerEmail) {
            $payload['customer_email'] = $request->customerEmail;
        }

        if ($request->mode === CheckoutMode::SUBSCRIPTION) {
            $payload['subscription_data'] = ['metadata' => $request->metadata];
        } else {
            $payload['payment_intent_data'] = ['metadata' => $request->metadata];
        }

        return $this->mapCheckout(
            $this->gateway->createCheckoutSession($payload, $request->idempotencyKey),
            $request->credentialContext,
        );
    }

    public function lookupCheckout(CheckoutLookupData $request): CheckoutSessionData
    {
        return $this->mapCheckout(
            $this->gateway->getCheckoutSession($request->checkoutSessionId),
            $request->credentialContext,
        );
    }

    public function lookupSubscription(SubscriptionLookupData $request): SubscriptionData
    {
        return $this->mapSubscription(
            $this->gateway->getSubscription($request->subscriptionId),
            $request->credentialContext,
        );
    }

    public function cancelSubscription(SubscriptionCancellationData $request): SubscriptionData
    {
        $payload = $request->atPeriodEnd
            ? $this->gateway->updateSubscription(
                $request->subscriptionId,
                ['cancel_at_period_end' => 'true'],
                $request->idempotencyKey,
            )
            : $this->gateway->cancelSubscription($request->subscriptionId, $request->idempotencyKey);

        return $this->mapSubscription($payload, $request->credentialContext);
    }

    public function createBillingPortal(BillingPortalRequestData $request): BillingPortalSessionData
    {
        $payload = $this->gateway->createBillingPortalSession([
            'customer' => $request->customerId,
            'return_url' => $request->returnUrl,
        ], $request->idempotencyKey);

        return new BillingPortalSessionData(
            provider: $this->getName(),
            id: (string) ($payload['id'] ?? ''),
            url: (string) ($payload['url'] ?? ''),
            credentialContext: $request->credentialContext,
            rawPayload: $payload,
        );
    }

    public function createProduct(
        string $credentialContext,
        string $name,
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): BillingProductData {
        $payload = $this->gateway->createProduct([
            'name' => $name,
            'metadata' => $metadata,
        ], $idempotencyKey);

        return new BillingProductData(
            provider: $this->getName(),
            id: (string) ($payload['id'] ?? ''),
            name: (string) ($payload['name'] ?? $name),
            active: (bool) ($payload['active'] ?? true),
            credentialContext: $credentialContext,
            metadata: is_array($payload['metadata'] ?? null) ? $payload['metadata'] : $metadata,
        );
    }

    public function createPrice(
        string $credentialContext,
        string $productId,
        int $unitAmount,
        string $currency,
        ?string $recurringInterval = null,
        ?string $idempotencyKey = null,
    ): BillingPriceData {
        $request = [
            'product' => $productId,
            'unit_amount' => $unitAmount,
            'currency' => strtolower($currency),
        ];

        if ($recurringInterval) {
            $request['recurring'] = ['interval' => $recurringInterval];
        }

        $payload = $this->gateway->createPrice($request, $idempotencyKey);

        return new BillingPriceData(
            provider: $this->getName(),
            id: (string) ($payload['id'] ?? ''),
            productId: (string) ($payload['product'] ?? $productId),
            unitAmount: (int) ($payload['unit_amount'] ?? $unitAmount),
            currency: strtoupper((string) ($payload['currency'] ?? $currency)),
            credentialContext: $credentialContext,
            recurringInterval: $this->nullableString(Arr::get($payload, 'recurring.interval')),
            active: (bool) ($payload['active'] ?? true),
        );
    }

    /** @param array<string, mixed> $payload */
    private function mapCheckout(array $payload, string $credentialContext): CheckoutSessionData
    {
        return new CheckoutSessionData(
            provider: $this->getName(),
            id: (string) ($payload['id'] ?? ''),
            mode: CheckoutMode::tryFrom((string) ($payload['mode'] ?? 'payment')) ?? CheckoutMode::PAYMENT,
            status: CheckoutStatusEnum::tryFrom((string) ($payload['status'] ?? 'open')) ?? CheckoutStatusEnum::OPEN,
            credentialContext: $credentialContext,
            url: $this->nullableString($payload['url'] ?? null),
            customerId: $this->idFromExpandable($payload['customer'] ?? null),
            subscriptionId: $this->idFromExpandable($payload['subscription'] ?? null),
            paymentId: $this->idFromExpandable($payload['payment_intent'] ?? null),
            paymentStatus: $this->nullableString($payload['payment_status'] ?? null),
            externalReference: $this->nullableString($payload['client_reference_id'] ?? null),
            amountTotal: isset($payload['amount_total']) ? (int) $payload['amount_total'] : null,
            currency: isset($payload['currency']) ? strtoupper((string) $payload['currency']) : null,
            expiresAt: $this->timestamp($payload['expires_at'] ?? null),
            rawPayload: $payload,
        );
    }

    /** @param array<string, mixed> $payload */
    private function mapSubscription(array $payload, string $credentialContext): SubscriptionData
    {
        return new SubscriptionData(
            provider: $this->getName(),
            id: (string) ($payload['id'] ?? ''),
            status: SubscriptionStatusEnum::fromProvider($payload['status'] ?? null),
            credentialContext: $credentialContext,
            customerId: $this->idFromExpandable($payload['customer'] ?? null),
            priceReference: $this->nullableString(Arr::get($payload, 'items.data.0.price.id')),
            currentPeriodStart: $this->timestamp(
                Arr::get($payload, 'items.data.0.current_period_start') ?? ($payload['current_period_start'] ?? null),
            ),
            currentPeriodEnd: $this->timestamp(
                Arr::get($payload, 'items.data.0.current_period_end') ?? ($payload['current_period_end'] ?? null),
            ),
            cancelAtPeriodEnd: (bool) ($payload['cancel_at_period_end'] ?? false),
            canceledAt: $this->timestamp($payload['canceled_at'] ?? null),
            rawPayload: $payload,
        );
    }

    private function timestamp(mixed $value): ?CarbonImmutable
    {
        return is_numeric($value) ? CarbonImmutable::createFromTimestampUTC((int) $value) : null;
    }

    private function idFromExpandable(mixed $value): ?string
    {
        return is_array($value)
            ? $this->nullableString($value['id'] ?? null)
            : $this->nullableString($value);
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
