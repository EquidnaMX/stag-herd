<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Stripe\Services;

use Equidna\StagHerd\Contracts\Gateways\StripeGateway;

final class StripeCardReuse
{
    public function __construct(
        private readonly StripeGateway $stripeGateway,
    ) {
    }

    /**
     * @return array{
     *     payment_method_id: string,
     *     original_payment_method_id: string,
     *     payment_method: array<string, mixed>,
     *     fingerprint: string|null,
     *     duplicated: bool
     * }
     */
    public function resolve(
        string $customerId,
        string $paymentMethodId,
    ): array {
        $paymentMethod = $this->stripeGateway->getPaymentMethod(
            $paymentMethodId,
        );

        $fingerprint = data_get(
            $paymentMethod,
            'card.fingerprint',
        );

        if (!is_string($fingerprint) || $fingerprint === '') {
            return [
                'payment_method_id' => $paymentMethodId,
                'original_payment_method_id' => $paymentMethodId,
                'payment_method' => $paymentMethod,
                'fingerprint' => null,
                'duplicated' => false,
            ];
        }

        $paymentMethods = $this->stripeGateway->listCustomerPaymentMethods(
            $customerId,
            'card',
        );

        foreach (data_get($paymentMethods, 'data', []) as $existingMethod) {
            $existingId = (string) data_get($existingMethod, 'id', '');
            $existingFingerprint = data_get(
                $existingMethod,
                'card.fingerprint',
            );

            if ($existingId === '' || $existingId === $paymentMethodId) {
                continue;
            }

            if ($existingFingerprint !== $fingerprint) {
                continue;
            }

            $this->stripeGateway->detachPaymentMethod($paymentMethodId);

            return [
                'payment_method_id' => $existingId,
                'original_payment_method_id' => $paymentMethodId,
                'payment_method' => $existingMethod,
                'fingerprint' => $fingerprint,
                'duplicated' => true,
            ];
        }

        return [
            'payment_method_id' => $paymentMethodId,
            'original_payment_method_id' => $paymentMethodId,
            'payment_method' => $paymentMethod,
            'fingerprint' => $fingerprint,
            'duplicated' => false,
        ];
    }
}
