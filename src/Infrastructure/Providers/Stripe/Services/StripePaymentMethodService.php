<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe\Services;

use Equidna\StagHerd\Application\Actions\RegisterPaymentMethod;
use Equidna\StagHerd\Data\PaymentMethodData;
use Equidna\StagHerd\Data\PaymentMethodRegisterData;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class StripePaymentMethodService
{
    public function __construct(
        private RegisterPaymentMethod $registerPaymentMethod,
    ) {
        //
    }

    /**
     * @param array<string, mixed> $paymentMethod
     * @param array<string, mixed> $sourcePayload
     */
    public function register(
        string $ownerReference,
        string $customerId,
        string $paymentMethodId,
        array $paymentMethod,
        array $sourcePayload = [],
        string $credentialContext = 'default',
    ): ?PaymentMethodData {
        $ownerReference = trim($ownerReference);
        $customerId = trim($customerId);
        $paymentMethodId = trim($paymentMethodId);
        $credentialContext = trim($credentialContext) !== ''
            ? trim($credentialContext)
            : 'default';

        if ($ownerReference === '' || $customerId === '' || $paymentMethodId === '') {
            return null;
        }

        return $this->registerPaymentMethod->handle(
            new PaymentMethodRegisterData(
                provider: 'stripe',
                ownerReference: $ownerReference,
                providerCustomerId: $customerId,
                providerPaymentMethodId: $paymentMethodId,
                credentialContext: $credentialContext,
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
            )
        );
    }

    /**
     * @param array<string, mixed> $paymentMethod
     * @param array<string, mixed> $sourcePayload
     */
    public function attemptRegister(
        string $ownerReference,
        string $customerId,
        string $paymentMethodId,
        array $paymentMethod,
        array $sourcePayload = [],
        string $credentialContext = 'default',
    ): ?PaymentMethodData {
        try {
            return $this->register(
                ownerReference: $ownerReference,
                customerId: $customerId,
                paymentMethodId: $paymentMethodId,
                paymentMethod: $paymentMethod,
                sourcePayload: $sourcePayload,
                credentialContext: $credentialContext,
            );
        } catch (Throwable $exception) {
            Log::warning('Failed to register Stripe payment method.', [
                'owner_reference' => $ownerReference,
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethodId,
                'credential_context' => $credentialContext,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
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
