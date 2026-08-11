<?php

namespace Equidna\StagHerd\Infrastructure\Providers\PayPal\Services;

use Equidna\StagHerd\Application\Actions\RegisterPaymentMethod;
use Equidna\StagHerd\Contracts\Gateways\PayPalGateway;
use Equidna\StagHerd\Data\PaymentMethodData;
use Equidna\StagHerd\Data\PaymentMethodRegisterData;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class PayPalPaymentMethodService
{
    public function __construct(
        private RegisterPaymentMethod $registerPaymentMethod,
        private PayPalGateway $gateway,
    ) {
        //
    }

    /**
     * @param array<string, mixed> $paymentToken
     */
    public function register(
        string $ownerReference,
        string $paymentTokenId,
        array $paymentToken = [],
        string $credentialContext = 'default',
    ): ?PaymentMethodData {
        $ownerReference = trim($ownerReference);
        $paymentTokenId = trim($paymentTokenId);
        $credentialContext = trim($credentialContext) !== ''
            ? trim($credentialContext)
            : 'default';

        if ($ownerReference === '' || $paymentTokenId === '') {
            return null;
        }

        $paymentToken = $paymentToken !== []
            ? $paymentToken
            : $this->gateway->getPaymentToken($paymentTokenId);

        $resolvedPaymentTokenId = $this->firstString([
            data_get($paymentToken, 'id'),
            $paymentTokenId,
        ]);

        if ($resolvedPaymentTokenId === null) {
            throw InvalidPaymentPayloadException::missingField('paypal_payment_token.id');
        }

        $customerId = $this->resolveCustomerId($paymentToken);

        if ($customerId === null) {
            throw InvalidPaymentPayloadException::missingField('paypal_payment_token.customer_id');
        }

        $card = $this->resolveCardPayload($paymentToken);
        [$expMonth, $expYear] = $this->resolveExpiry($paymentToken);

        return $this->registerPaymentMethod->handle(
            new PaymentMethodRegisterData(
                provider: 'paypal',
                ownerReference: $ownerReference,
                providerCustomerId: $customerId,
                providerPaymentMethodId: $resolvedPaymentTokenId,
                credentialContext: $credentialContext,
                type: 'tokenized_card',
                displayName: $this->resolveDisplayName($paymentToken),
                brand: $this->firstString([
                    data_get($card, 'brand'),
                    data_get($card, 'type'),
                    data_get($card, 'network'),
                ]),
                last4: $this->firstString([
                    data_get($card, 'last_digits'),
                    data_get($card, 'last4'),
                ]),
                expMonth: $expMonth,
                expYear: $expYear,
                providerEventCreatedAt: $this->toTimestamp(
                    $this->firstString([
                        data_get($paymentToken, 'create_time'),
                        data_get($paymentToken, 'creation_time'),
                    ])
                ) ?? 0,
                payload: $this->cleanPayload([
                    'paypal' => [
                        'token_id' => $resolvedPaymentTokenId,
                        'token_type' => 'PAYMENT_TOKEN',
                        'customer_id' => $customerId,
                        'payment_source' => [
                            'token' => [
                                'id' => $resolvedPaymentTokenId,
                                'type' => 'PAYMENT_TOKEN',
                            ],
                        ],
                        'card' => $card,
                        'payment_token' => $paymentToken,
                    ],
                ]),
            )
        );
    }

    /**
     * @param array<string, mixed> $paymentToken
     */
    public function attemptRegister(
        string $ownerReference,
        string $paymentTokenId,
        array $paymentToken = [],
        string $credentialContext = 'default',
    ): ?PaymentMethodData {
        try {
            return $this->register(
                ownerReference: $ownerReference,
                paymentTokenId: $paymentTokenId,
                paymentToken: $paymentToken,
                credentialContext: $credentialContext,
            );
        } catch (Throwable $exception) {
            Log::warning('Failed to register PayPal payment method.', [
                'owner_reference' => $ownerReference,
                'payment_token_id' => $paymentTokenId,
                'credential_context' => $credentialContext,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $paymentToken
     */
    private function resolveCustomerId(array $paymentToken): ?string
    {
        return $this->firstString([
            data_get($paymentToken, 'customer.id'),
            data_get($paymentToken, 'customer_id'),
            data_get($paymentToken, 'payment_source.card.attributes.customer.id'),
            data_get($paymentToken, 'payment_source.card.attributes.vault.customer.id'),
        ]);
    }

    /**
     * @param array<string, mixed> $paymentToken
     * @return array<string, mixed>
     */
    private function resolveCardPayload(array $paymentToken): array
    {
        $card = data_get($paymentToken, 'payment_source.card');

        return is_array($card) ? $card : [];
    }

    /**
     * @param array<string, mixed> $paymentToken
     * @return array{0: ?int, 1: ?int}
     */
    private function resolveExpiry(array $paymentToken): array
    {
        $month = $this->firstInt([
            data_get($paymentToken, 'payment_source.card.exp_month'),
            data_get($paymentToken, 'payment_source.card.expiry_month'),
        ]);

        $year = $this->firstInt([
            data_get($paymentToken, 'payment_source.card.exp_year'),
            data_get($paymentToken, 'payment_source.card.expiry_year'),
        ]);

        if ($month !== null && $year !== null) {
            return [$month, $year];
        }

        $expiry = $this->firstString([
            data_get($paymentToken, 'payment_source.card.expiry'),
        ]);

        if ($expiry !== null && preg_match('/^(?<year>\d{4})-(?<month>\d{2})$/', $expiry, $matches) === 1) {
            return [
                (int) $matches['month'],
                (int) $matches['year'],
            ];
        }

        return [$month, $year];
    }

    /**
     * @param array<string, mixed> $paymentToken
     */
    private function resolveDisplayName(array $paymentToken): ?string
    {
        $fullName = trim(implode(' ', array_filter([
            $this->firstString([
                data_get($paymentToken, 'payment_source.paypal.name.given_name'),
            ]),
            $this->firstString([
                data_get($paymentToken, 'payment_source.paypal.name.surname'),
            ]),
        ])));

        if ($fullName !== '') {
            return $fullName;
        }

        return $this->firstString([
            data_get($paymentToken, 'payment_source.card.name'),
            data_get($paymentToken, 'payer.name.given_name'),
            data_get($paymentToken, 'customer.name'),
        ]);
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
            if (! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
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

    private function toTimestamp(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : $timestamp;
    }
}
