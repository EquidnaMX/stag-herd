<?php

namespace Equidna\StagHerd\Infrastructure\Providers\MercadoPago\Services;

use Equidna\StagHerd\Application\Actions\RegisterPaymentMethod;
use Equidna\StagHerd\Contracts\Gateways\MercadoPagoGateway;
use Equidna\StagHerd\Data\PaymentMethodData;
use Equidna\StagHerd\Data\PaymentMethodRegisterData;
use Equidna\StagHerd\Exceptions\InvalidPaymentPayloadException;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class MercadoPagoPaymentMethodService
{
    public function __construct(
        private RegisterPaymentMethod $registerPaymentMethod,
        private MercadoPagoGateway $gateway,
    ) {
        //
    }

    /**
     * @param array<string, mixed> $card
     */
    public function register(
        string $ownerReference,
        string $customerId,
        string $cardId,
        array $card = [],
        string $credentialContext = 'default',
    ): ?PaymentMethodData {
        $ownerReference = trim($ownerReference);
        $customerId = trim($customerId);
        $cardId = trim($cardId);
        $credentialContext = trim($credentialContext) !== ''
            ? trim($credentialContext)
            : 'default';

        if ($ownerReference === '' || $customerId === '' || $cardId === '') {
            return null;
        }

        $card = $card !== []
            ? $this->normalizeCardPayload($card)
            : $this->findCustomerCard($customerId, $cardId);

        $resolvedCardId = $this->firstString([
            data_get($card, 'id'),
            $cardId,
        ]);

        if ($resolvedCardId === null) {
            throw InvalidPaymentPayloadException::missingField('mercado_pago_card.id');
        }

        $resolvedCustomerId = $this->firstString([
            data_get($card, 'customer_id'),
            $customerId,
        ]);

        if ($resolvedCustomerId === null) {
            throw InvalidPaymentPayloadException::missingField('mercado_pago_card.customer_id');
        }

        $paymentMethod = $this->resolvePaymentMethodPayload($card);

        return $this->registerPaymentMethod->handle(
            new PaymentMethodRegisterData(
                provider: 'mercado_pago',
                ownerReference: $ownerReference,
                providerCustomerId: $resolvedCustomerId,
                providerPaymentMethodId: $resolvedCardId,
                credentialContext: $credentialContext,
                type: 'tokenized_card',
                displayName: $this->resolveDisplayName($card),
                brand: $this->firstString([
                    data_get($paymentMethod, 'id'),
                    data_get($paymentMethod, 'name'),
                ]),
                last4: $this->firstString([
                    data_get($card, 'last_four_digits'),
                ]),
                expMonth: $this->firstInt([
                    data_get($card, 'expiration_month'),
                ]),
                expYear: $this->firstInt([
                    data_get($card, 'expiration_year'),
                ]),
                providerEventCreatedAt: $this->toTimestamp(
                    $this->firstString([
                        data_get($card, 'date_created'),
                        data_get($card, 'date_last_updated'),
                    ])
                ) ?? 0,
                payload: $this->cleanPayload([
                    'mercado_pago' => [
                        'customer_id' => $resolvedCustomerId,
                        'card_id' => $resolvedCardId,
                        'payment_method_id' => $this->firstString([
                            data_get($paymentMethod, 'id'),
                        ]),
                        'issuer_id' => data_get($card, 'issuer.id'),
                        'first_six_digits' => $this->firstString([
                            data_get($card, 'first_six_digits'),
                        ]),
                        'last_four_digits' => $this->firstString([
                            data_get($card, 'last_four_digits'),
                        ]),
                        'cardholder' => $this->arrayOrEmpty(data_get($card, 'cardholder')),
                        'issuer' => $this->arrayOrEmpty(data_get($card, 'issuer')),
                        'payment_method' => $paymentMethod,
                        'security_code' => $this->arrayOrEmpty(data_get($card, 'security_code')),
                        'card' => $card,
                    ],
                ]),
            )
        );
    }

    /**
     * @param array<string, mixed> $card
     */
    public function attemptRegister(
        string $ownerReference,
        string $customerId,
        string $cardId,
        array $card = [],
        string $credentialContext = 'default',
    ): ?PaymentMethodData {
        try {
            return $this->register(
                ownerReference: $ownerReference,
                customerId: $customerId,
                cardId: $cardId,
                card: $card,
                credentialContext: $credentialContext,
            );
        } catch (Throwable $exception) {
            Log::warning('Failed to register Mercado Pago payment method.', [
                'owner_reference' => $ownerReference,
                'customer_id' => $customerId,
                'card_id' => $cardId,
                'credential_context' => $credentialContext,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $card
     * @return array<string, mixed>
     */
    private function normalizeCardPayload(array $card): array
    {
        $nestedCard = data_get($card, 'card');

        if (
            (!array_key_exists('id', $card) || !is_scalar($card['id']))
            && is_array($nestedCard)
            && $nestedCard !== []
        ) {
            return $nestedCard;
        }

        return $card;
    }

    /**
     * @return array<string, mixed>
     */
    private function findCustomerCard(string $customerId, string $cardId): array
    {
        $cards = $this->gateway->getCustomerCards($customerId);

        foreach ($cards as $card) {
            if (!is_array($card)) {
                continue;
            }

            if ((string) data_get($card, 'id') !== $cardId) {
                continue;
            }

            return $card;
        }

        throw InvalidPaymentPayloadException::invalidField(
            'card_id',
            'Mercado Pago customer does not have the requested card.'
        );
    }

    /**
     * @param array<string, mixed> $card
     * @return array<string, mixed>
     */
    private function resolvePaymentMethodPayload(array $card): array
    {
        $paymentMethod = data_get($card, 'payment_method');

        return is_array($paymentMethod) ? $paymentMethod : [];
    }

    /**
     * @param array<string, mixed> $card
     */
    private function resolveDisplayName(array $card): ?string
    {
        return $this->firstString([
            data_get($card, 'cardholder.name'),
            data_get($card, 'cardholder.first_name'),
            data_get($card, 'cardholder.last_name'),
        ]);
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
            if (!is_scalar($value)) {
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

    /**
     * @return array<string, mixed>
     */
    private function arrayOrEmpty(mixed $value): array
    {
        return is_array($value) ? $value : [];
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
