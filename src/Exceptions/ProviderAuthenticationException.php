<?php

namespace Equidna\StagHerd\Exceptions;

use Equidna\Toolkit\Exceptions\UnauthorizedException;

class ProviderAuthenticationException extends UnauthorizedException
{
    public static function invalidCredentials(string $provider): self
    {
        return new self(
            message: sprintf(
                'Payment provider [%s] credentials are invalid.',
                $provider,
            ),
            errors: [
                'provider' => $provider,
            ],
        );
    }

    public static function unauthorized(
        string $provider,
        array $response = [],
    ): self {
        return new self(
            message: sprintf(
                'Payment provider [%s] rejected the authentication request.',
                $provider,
            ),
            errors: [
                'provider' => $provider,
                'response' => $response,
            ],
        );
    }
}
