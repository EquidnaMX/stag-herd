<?php

namespace Equidna\StagHerd\Exceptions;

use Equidna\Toolkit\Exceptions\NotFoundException;

class PaymentNotFoundException extends NotFoundException
{
    public static function withId(int|string $id): self
    {
        return new self(
            message: sprintf(
                'Payment [%s] was not found.',
                $id,
            ),
            errors: [
                'payment_id' => $id,
            ],
        );
    }

    public static function withExternalReference(string $externalReference): self
    {
        return new self(
            message: sprintf(
                'Payment with external reference [%s] was not found.',
                $externalReference,
            ),
            errors: [
                'external_reference' => $externalReference,
            ],
        );
    }

    public static function withProviderReference(
        string $provider,
        string $reference,
    ): self {
        return new self(
            message: sprintf(
                'Payment with provider reference [%s] was not found for provider [%s].',
                $reference,
                $provider,
            ),
            errors: [
                'provider' => $provider,
                'reference' => $reference,
            ],
        );
    }
}
