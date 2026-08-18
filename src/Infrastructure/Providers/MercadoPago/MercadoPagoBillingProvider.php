<?php

namespace Equidna\StagHerd\Infrastructure\Providers\MercadoPago;

use Carbon\CarbonImmutable;
use Equidna\StagHerd\Contracts\BillingCatalogProvider;
use Equidna\StagHerd\Contracts\BillingProvider;
use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
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

final readonly class MercadoPagoBillingProvider implements
    BillingProvider,
    HostedCheckoutProvider,
    ManagesSubscriptions,
    BillingCatalogProvider
{
    public function __construct(
        private MercadoPagoGateway $gateway,
    ) {
        //
    }

    public function getName(): string
    {
        return 'mercado_pago';
    }

    public function createCheckout(CheckoutRequestData $request): CheckoutSessionData
    {
        if ($request->mode !== CheckoutMode::SUBSCRIPTION) {
            throw UnsupportedOperationException::forOperation(
                'hosted checkout',
                'Mercado Pago billing checkout only supports subscription mode.',
            );
        }

        if (count($request->lineItems) !== 1) {
            throw UnsupportedOperationException::forOperation(
                'hosted checkout',
                'Mercado Pago billing checkout requires exactly one subscription line item.',
            );
        }

        $lineItem = $request->lineItems[0];

        $payload = array_filter([
            'preapproval_plan_id' => $lineItem->priceReference,
            'reason' => $this->nullableString($request->externalReference) ?? 'Subscription',
            'external_reference' => $this->nullableString($request->externalReference),
            'payer_email' => $request->customerEmail,
            'back_url' => $request->successUrl,
            'status' => 'pending',
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $response = $this->gateway->createPreapproval($payload, $request->idempotencyKey);

        return $this->mapCheckout($response, $request->credentialContext);
    }

    public function lookupCheckout(CheckoutLookupData $request): CheckoutSessionData
    {
        return $this->mapCheckout(
            $this->gateway->getPreapproval($request->checkoutSessionId),
            $request->credentialContext,
        );
    }

    public function lookupSubscription(SubscriptionLookupData $request): SubscriptionData
    {
        return $this->mapSubscription(
            $this->gateway->getPreapproval($request->subscriptionId),
            $request->credentialContext,
        );
    }

    public function cancelSubscription(SubscriptionCancellationData $request): SubscriptionData
    {
        if ($request->atPeriodEnd) {
            throw UnsupportedOperationException::forOperation(
                'subscription cancellation',
                'Mercado Pago subscriptions do not support cancel_at_period_end scheduling.',
            );
        }

        $this->gateway->updatePreapproval(
            $request->subscriptionId,
            ['status' => 'cancelled'],
            $request->idempotencyKey,
        );

        return $this->mapSubscription(
            $this->gateway->getPreapproval($request->subscriptionId),
            $request->credentialContext,
        );
    }

    public function createProduct(
        string $credentialContext,
        string $name,
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): BillingProductData {
        throw UnsupportedOperationException::forOperation(
            'product creation',
            'Mercado Pago recurring billing does not expose a native product catalog resource.',
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
        $recurrence = $this->mercadoPagoRecurrence($recurringInterval);

        $response = $this->gateway->createPreapprovalPlan([
            'reason' => $productId,
            'back_url' => rtrim((string) config('app.url'), '/'),
            'status' => 'active',
            'auto_recurring' => [
                'frequency' => $recurrence['frequency'],
                'frequency_type' => $recurrence['frequency_type'],
                'transaction_amount' => MoneyFormatter::toDecimal($unitAmount),
                'currency_id' => strtoupper($currency),
            ],
        ], $idempotencyKey);

        return new BillingPriceData(
            provider: $this->getName(),
            id: (string) ($response['id'] ?? ''),
            productId: $productId,
            unitAmount: $this->unitAmountFromPlan($response) ?? $unitAmount,
            currency: strtoupper((string) (Arr::get($response, 'auto_recurring.currency_id') ?? $currency)),
            credentialContext: $credentialContext,
            recurringInterval: $this->sharedIntervalFromMercadoPago($response, $recurrence),
            active: strtolower((string) ($response['status'] ?? 'active')) === 'active',
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
            url: $this->approvalUrl($payload),
            customerId: $this->nullableString($payload['payer_email'] ?? null),
            subscriptionId: $subscriptionId !== '' ? $subscriptionId : null,
            paymentStatus: $this->nullableString($payload['status'] ?? null),
            externalReference: $this->nullableString($payload['external_reference'] ?? null),
            rawPayload: $payload,
        );
    }

    /** @param array<string, mixed> $payload */
    private function mapSubscription(array $payload, string $credentialContext): SubscriptionData
    {
        $status = strtolower((string) ($payload['status'] ?? ''));

        return new SubscriptionData(
            provider: $this->getName(),
            id: (string) ($payload['id'] ?? ''),
            status: $this->mapSubscriptionStatus($status),
            credentialContext: $credentialContext,
            customerId: $this->nullableString($payload['payer_email'] ?? null),
            priceReference: $this->nullableString(
                $payload['preapproval_plan_id']
                    ?? Arr::get($payload, 'auto_recurring.preapproval_plan_id')
                    ?? null
            ),
            currentPeriodStart: $this->timestamp(
                $payload['date_created']
                    ?? Arr::get($payload, 'auto_recurring.start_date')
            ),
            currentPeriodEnd: $this->timestamp(
                $payload['next_payment_date']
                    ?? Arr::get($payload, 'auto_recurring.end_date')
            ),
            cancelAtPeriodEnd: false,
            canceledAt: $status === 'cancelled'
                ? $this->timestamp($payload['last_modified'] ?? null)
                : null,
            rawPayload: $payload,
        );
    }

    /** @param array<string, mixed> $payload */
    private function mapCheckoutStatus(array $payload): CheckoutStatusEnum
    {
        return match (strtolower((string) ($payload['status'] ?? ''))) {
            'authorized', 'active' => CheckoutStatusEnum::COMPLETE,
            'cancelled', 'paused' => CheckoutStatusEnum::EXPIRED,
            default => CheckoutStatusEnum::OPEN,
        };
    }

    private function mapSubscriptionStatus(string $status): SubscriptionStatusEnum
    {
        return match ($status) {
            'authorized', 'active' => SubscriptionStatusEnum::ACTIVE,
            'pending' => SubscriptionStatusEnum::INCOMPLETE,
            'paused' => SubscriptionStatusEnum::PAUSED,
            'cancelled' => SubscriptionStatusEnum::CANCELED,
            default => SubscriptionStatusEnum::UNKNOWN,
        };
    }

    /**
     * @return array{frequency:int, frequency_type:string}
     */
    private function mercadoPagoRecurrence(?string $recurringInterval): array
    {
        return match (strtolower((string) $recurringInterval)) {
            'day', 'daily' => ['frequency' => 1, 'frequency_type' => 'days'],
            'week', 'weekly' => ['frequency' => 7, 'frequency_type' => 'days'],
            'month', 'monthly' => ['frequency' => 1, 'frequency_type' => 'months'],
            'year', 'yearly', 'annual', 'annually' => ['frequency' => 12, 'frequency_type' => 'months'],
            default => throw UnsupportedOperationException::forOperation(
                'price creation',
                'Mercado Pago recurring plans require a supported recurring interval: day, week, month, or year.',
            ),
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{frequency:int, frequency_type:string} $fallback
     */
    private function sharedIntervalFromMercadoPago(array $payload, array $fallback): string
    {
        $frequency = (int) (Arr::get($payload, 'auto_recurring.frequency') ?? $fallback['frequency']);
        $frequencyType = strtolower((string) (Arr::get($payload, 'auto_recurring.frequency_type') ?? $fallback['frequency_type']));

        return match (true) {
            $frequencyType === 'days' && $frequency === 1 => 'day',
            $frequencyType === 'days' && $frequency === 7 => 'week',
            $frequencyType === 'months' && $frequency === 1 => 'month',
            $frequencyType === 'months' && $frequency === 12 => 'year',
            default => $frequencyType,
        };
    }

    /** @param array<string, mixed> $payload */
    private function approvalUrl(array $payload): ?string
    {
        return $this->nullableString(
            $payload['init_point']
                ?? $payload['sandbox_init_point']
                ?? $payload['back_url']
                ?? null
        );
    }

    /** @param array<string, mixed> $payload */
    private function unitAmountFromPlan(array $payload): ?int
    {
        $value = Arr::get($payload, 'auto_recurring.transaction_amount');

        return $value === null || $value === ''
            ? null
            : MoneyFormatter::fromDecimal((string) $value);
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
