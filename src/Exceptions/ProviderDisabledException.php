<?php

namespace Equidna\StagHerd\Exceptions;

use Equidna\Toolkit\Exceptions\ForbiddenException;

class ProviderDisabledException extends ForbiddenException
{
    public static function forProvider(string $provider): self
    {
        return new self(
            message: sprintf(
                'Payment provider [%s] is disabled.',
                $provider,
            ),
            errors: [
                'provider' => $provider,
            ],
        );
    }
}
