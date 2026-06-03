<?php

namespace Equidna\StagHerd\Exceptions;

use Equidna\Toolkit\Exceptions\NotFoundException;

class ProviderNotRegisteredException extends NotFoundException
{
    public static function forProvider(string $provider): self
    {
        return new self(
            message: sprintf(
                'Payment provider [%s] is not registered.',
                $provider,
            ),
            errors: [
                'provider' => $provider,
            ],
        );
    }
}
