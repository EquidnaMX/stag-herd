<?php

namespace Equidna\StagHerd\Infrastructure\Providers\PayPal;

use Carbon\CarbonImmutable;
use Equidna\StagHerd\Contracts\BillingCatalogProvider;
use Equidna\StagHerd\Contracts\BillingProvider;
use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Contracts\HostedCheckoutProvider;
use Equidna\StagHerd\Contracts\ManagesSubscriptions;
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
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;
use Equidna\StagHerd\Support\MoneyFormatter;
use Illuminate\Support\Arr;

final readonly class PayPalBillingProvider implements
    BillingProvider,
    HostedCheckoutProvider,
    ManagesSubscriptions,
    BillingCatalogProvider
{
    public function __construct(
        private PayPalGateway $gateway,
    ) {
        //
    }

    public function getName(): string
    {
        return 'paypal';
    }

    public function createCheckout(CheckoutRequestData $request): CheckoutSessionData
    {
        if ($request->mode !== CheckoutMode::SUBSCRIPTION) {
            throw UnsupportedOperationException::forOperation(
                'hosted checkout',
                'PayPal billing checkout only supports subscription mode.',
            );
        }

        if (count($request->lineItems) !== 1) {
            throw UnsupportedOperationException::forOperation(
                'hosted checkout',
                'PayPal billing checkout requires exactly one subscription line item.',
            );
        }

        $lineItem = $request->lineItems[0];

        $payload = array_filter([
            'plan_id' => $lineItem->priceReference,
            'quantity' => (string) max(1, $lineItem->quantity),
            'custom_id' => $this->nullableString($request->externalReference),
            'subscriber' => $request->customerEmail
                ? ['email_address' => $request->customerEmail]
                : null,
            'application_context' => array_filter([
                'brand_name' => config('app.name'),
                'return_url' => $request->successUrl,
                'cancel_url' => $request->cancelUrl,
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'SUBSCRIBE_NOW',
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        ], fn (mixed $value): bool => $value !== null);

        $response = $this->gateway->createSubscription($payload, $request->idempotencyKey);

        return $this->mapCheckout($response, $request->credentialContext);
    }

    public function lookupCheckout(CheckoutLookupData $request): CheckoutSessionData
    {
        return $this->mapCheckout(
            $this->gateway->getSubscription($request->checkoutSessionId),
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
        if ($request->atPeriodEnd) {
            throw UnsupportedOperationException::forOperation(
                'subscription cancellation',
                'PayPal subscriptions do not support cancel_at_period_end scheduling.',
            );
        }

        $this->gateway->cancelSubscription(
            $request->subscriptionId,
            ['reason' => 'Canceled by merchant request.'],
            $request->idempotencyKey,
        );

        return $this->mapSubscription(
            $this->gateway->getSubscription($request->subscriptionId),
            $request->credentialContext,
        );
    }

    public function createProduct(
        string $credentialContext,
        string $name,
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): BillingProductData {
        $payload = array_filter([
            'name' => $name,
            'description' => $this->nullableString($metadata['description'] ?? null),
            'type' => 'SERVICE',
            'category' => $this->nullableString($metadata['category'] ?? null),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $response = $this->gateway->createCatalogProduct($payload, $idempotencyKey);

        return new BillingProductData(
            provider: $this->getName(),
            id: (string) ($response['id'] ?? ''),
            name: (string) ($response['name'] ?? $name),
            active: strtoupper((string) ($response['status'] ?? 'ACTIVE')) === 'ACTIVE',
            credentialContext: $credentialContext,
            metadata: array_filter([
                'description' => $this->nullableString($response['description'] ?? ($payload['description'] ?? null)),
                'category' => $this->nullableString($response['category'] ?? ($payload['category'] ?? null)),
            ], fn (?string $value): bool => $value !== null),
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
        $intervalUnit = $this->paypalInterval($recurringInterval);

        $response = $this->gateway->createPlan([
            'product_id' => $productId,
            'name' => sprintf('%s-%s', $productId, strtolower($intervalUnit)),
            'status' => 'ACTIVE',
            'quantity_supported' => true,
            'billing_cycles' => [[
                'frequency' => [
                    'interval_unit' => $intervalUnit,
                    'interval_count' => 1,
                ],
                'tenure_type' => 'REGULAR',
                'sequence' => 1,
                'total_cycles' => 0,
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => MoneyFormatter::toDecimalString($unitAmount),
                        'currency_code' => strtoupper($currency),
                    ],
                ],
            ]],
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee_failure_action' => 'CANCEL',
                'payment_failure_threshold' => 1,
            ],
        ], $idempotencyKey);

        return new BillingPriceData(
            provider: $this->getName(),
            id: (string) ($response['id'] ?? ''),
            productId: (string) ($response['product_id'] ?? $productId),
            unitAmount: $this->unitAmountFromPlan($response) ?? $unitAmount,
            currency: strtoupper((string) (Arr::get($response, 'billing_cycles.0.pricing_scheme.fixed_price.currency_code') ?? $currency)),
            credentialContext: $credentialContext,
            recurringInterval: strtolower((string) (Arr::get($response, 'billing_cycles.0.frequency.interval_unit') ?? $intervalUnit)),
            active: strtoupper((string) ($response['status'] ?? 'ACTIVE')) === 'ACTIVE',
        );
    }

    /** @param array<string, mixed> $payload */
    private function mapCheckout(array $payload, string $credentialContext): CheckoutSessionData
    {
        $subscriptionId = (string) ($payload['id'] ?? '');

        return new CheckoutSessionData(
            provider: $this->getName(),
            id: $subscriptionId,
            mode: CheckoutMode::SUBSCRIPTION,
            status: $this->mapCheckoutStatus($payload),
            credentialContext: $credentialContext,
            url: $this->approveUrl($payload),
            customerId: $this->customerReference($payload),
            subscriptionId: $subscriptionId !== '' ? $subscriptionId : null,
            paymentStatus: $this->nullableString($payload['status'] ?? null),
            externalReference: $this->nullableString($payload['custom_id'] ?? null),
            rawPayload: $payload,
        );
    }

    /** @param array<string, mixed> $payload */
    private function mapSubscription(array $payload, string $credentialContext): SubscriptionData
    {
        $status = strtoupper((string) ($payload['status'] ?? ''));

        return new SubscriptionData(
            provider: $this->getName(),
            id: (string) ($payload['id'] ?? ''),
            status: $this->mapSubscriptionStatus($status),
            credentialContext: $credentialContext,
            customerId: $this->customerReference($payload),
            priceReference: $this->nullableString($payload['plan_id'] ?? null),
            currentPeriodStart: $this->timestamp($payload['start_time'] ?? null),
            currentPeriodEnd: $this->timestamp(Arr::get($payload, 'billing_info.next_billing_time')),
            cancelAtPeriodEnd: false,
            canceledAt: in_array($status, ['CANCELLED', 'CANCELED'], true)
                ? $this->timestamp($payload['status_update_time'] ?? null)
                : null,
            rawPayload: $payload,
        );
    }

    /** @param array<string, mixed> $payload */
    private function mapCheckoutStatus(array $payload): CheckoutStatusEnum
    {
        return match (strtoupper((string) ($payload['status'] ?? ''))) {
            'ACTIVE' => CheckoutStatusEnum::COMPLETE,
            'CANCELLED', 'CANCELED', 'EXPIRED' => CheckoutStatusEnum::EXPIRED,
            default => CheckoutStatusEnum::OPEN,
        };
    }

    private function mapSubscriptionStatus(string $status): SubscriptionStatusEnum
    {
        return match ($status) {
            'ACTIVE' => SubscriptionStatusEnum::ACTIVE,
            'APPROVAL_PENDING', 'APPROVED', 'CREATED' => SubscriptionStatusEnum::INCOMPLETE,
            'SUSPENDED' => SubscriptionStatusEnum::PAUSED,
            'CANCELLED', 'CANCELED', 'EXPIRED' => SubscriptionStatusEnum::CANCELED,
            default => SubscriptionStatusEnum::UNKNOWN,
        };
    }

    private function paypalInterval(?string $recurringInterval): string
    {
        return match (strtolower((string) $recurringInterval)) {
            'day', 'daily' => 'DAY',
            'week', 'weekly' => 'WEEK',
            'month', 'monthly' => 'MONTH',
            'year', 'yearly', 'annual', 'annually' => 'YEAR',
            default => throw UnsupportedOperationException::forOperation(
                'price creation',
                'PayPal billing plans require a supported recurring interval: day, week, month, or year.',
            ),
        };
    }

    /** @param array<string, mixed> $payload */
    private function approveUrl(array $payload): ?string
    {
        $links = $payload['links'] ?? [];

        if (!is_array($links)) {
            return null;
        }

        foreach ($links as $link) {
            if (($link['rel'] ?? null) === 'approve' && !empty($link['href'])) {
                return (string) $link['href'];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function customerReference(array $payload): ?string
    {
        return $this->nullableString(
            Arr::get($payload, 'subscriber.payer_id')
                ?? Arr::get($payload, 'subscriber.email_address')
                ?? Arr::get($payload, 'subscriber.payer_email')
        );
    }

    /** @param array<string, mixed> $payload */
    private function unitAmountFromPlan(array $payload): ?int
    {
        $value = Arr::get($payload, 'billing_cycles.0.pricing_scheme.fixed_price.value');

        return $value === null || $value === '' ? null : MoneyFormatter::fromDecimal((string) $value);
    }

    private function timestamp(mixed $value): ?CarbonImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value);
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
