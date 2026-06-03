<?php

namespace Equidna\StagHerd\Exceptions;

use Equidna\Toolkit\Exceptions\ForbiddenException;

class PaymentMethodDisabledException extends ForbiddenException
{
    public static function forProvider(
        string $provider,
        string $method,
    ): self {
        return new self(
            message: sprintf(
                'Payment method [%s] is disabled for provider [%s].',
                $method,
                $provider,
            ),
            errors: [
                'provider' => $provider,
                'method' => $method,
            ],
        );
    }
}
