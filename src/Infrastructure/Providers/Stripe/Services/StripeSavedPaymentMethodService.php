<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe\Services;

use Equidna\StagHerd\Contracts\ManagesSavedPaymentMethods;
use Equidna\StagHerd\Data\SavedPaymentMethodData;
use Equidna\StagHerd\Data\SavedPaymentMethodUpsertData;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class StripeSavedPaymentMethodService
{
    public function __construct(
        private ManagesSavedPaymentMethods $savedPaymentMethods,
    ) {
        //
    }

    /**
     * @param array<string, mixed> $paymentMethod
     * @param array<string, mixed> $sourcePayload
     */
    public function persist(
        string $ownerReference,
        string $customerId,
        string $paymentMethodId,
        array $paymentMethod,
        array $sourcePayload = [],
    ): ?SavedPaymentMethodData {
        $ownerReference = trim($ownerReference);
        $customerId = trim($customerId);
        $paymentMethodId = trim($paymentMethodId);

        if ($ownerReference === '' || $customerId === '' || $paymentMethodId === '') {
            return null;
        }

        return $this->savedPaymentMethods->upsert(new SavedPaymentMethodUpsertData(
            provider: 'stripe',
            ownerReference: $ownerReference,
            providerCustomerId: $customerId,
            providerPaymentMethodId: $paymentMethodId,
            fingerprint: $this->firstString([
                data_get($paymentMethod, 'card.fingerprint'),
                data_get($sourcePayload, 'payment_method_details.card.fingerprint'),
                data_get($sourcePayload, 'charges.data.0.payment_method_details.card.fingerprint'),
            ]),
            displayName: $this->firstString([
                data_get($paymentMethod, 'billing_details.name'),
                data_get($sourcePayload, 'payment_method.billing_details.name'),
            ]),
            brand: $this->firstString([
                data_get($paymentMethod, 'card.brand'),
                data_get($sourcePayload, 'payment_method_details.card.brand'),
                data_get($sourcePayload, 'charges.data.0.payment_method_details.card.brand'),
            ]),
            last4: $this->firstString([
                data_get($paymentMethod, 'card.last4'),
                data_get($sourcePayload, 'payment_method_details.card.last4'),
                data_get($sourcePayload, 'charges.data.0.payment_method_details.card.last4'),
            ]),
            expMonth: $this->firstInt([
                data_get($paymentMethod, 'card.exp_month'),
                data_get($sourcePayload, 'payment_method_details.card.exp_month'),
                data_get($sourcePayload, 'charges.data.0.payment_method_details.card.exp_month'),
            ]),
            expYear: $this->firstInt([
                data_get($paymentMethod, 'card.exp_year'),
                data_get($sourcePayload, 'payment_method_details.card.exp_year'),
                data_get($sourcePayload, 'charges.data.0.payment_method_details.card.exp_year'),
            ]),
            payload: $this->cleanPayload([
                'payment_method' => $paymentMethod,
                'source_payload' => $sourcePayload,
            ]),
        ));
    }

    /**
     * @param array<string, mixed> $paymentMethod
     * @param array<string, mixed> $sourcePayload
     */
    public function attemptPersist(
        string $ownerReference,
        string $customerId,
        string $paymentMethodId,
        array $paymentMethod,
        array $sourcePayload = [],
    ): ?SavedPaymentMethodData {
        try {
            return $this->persist(
                ownerReference: $ownerReference,
                customerId: $customerId,
                paymentMethodId: $paymentMethodId,
                paymentMethod: $paymentMethod,
                sourcePayload: $sourcePayload,
            );
        } catch (Throwable $exception) {
            Log::warning('Failed to persist Stripe saved payment method.', [
                'owner_reference' => $ownerReference,
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethodId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /** @param array<string, mixed> $payload */
    private function cleanPayload(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->cleanPayload($value);

                if ($nested === []) {
                    continue;
                }

                $clean[$key] = $nested;

                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    /** @param array<int, mixed> $values */
    private function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    /** @param array<int, mixed> $values */
    private function firstInt(array $values): ?int
    {
        foreach ($values as $value) {
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }
}
