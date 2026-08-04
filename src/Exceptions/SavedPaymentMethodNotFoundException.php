<?php

namespace Equidna\StagHerd\Exceptions;

use Equidna\Toolkit\Exceptions\NotFoundException;

class SavedPaymentMethodNotFoundException extends NotFoundException
{
    public static function forOwner(
        string $provider,
        string $ownerReference,
        ?string $providerPaymentMethodId = null,
    ): self {
        return new self(
            message: $providerPaymentMethodId !== null
                ? sprintf(
                    'Saved payment method [%s] was not found for provider [%s] and owner [%s].',
                    $providerPaymentMethodId,
                    $provider,
                    $ownerReference,
                )
                : sprintf(
                    'No active saved payment methods were found for provider [%s] and owner [%s].',
                    $provider,
                    $ownerReference,
                ),
            errors: array_filter([
                'provider' => $provider,
                'owner_reference' => $ownerReference,
                'provider_payment_method_id' => $providerPaymentMethodId,
            ], fn ($value) => $value !== null),
        );
    }
}
