<?php

namespace Equidna\StagHerd\Exceptions;

use Equidna\Toolkit\Exceptions\UnauthorizedException;

class InvalidWebhookSignatureException extends UnauthorizedException
{
    public static function forProvider(string $provider): self
    {
        return new self(
            message: sprintf(
                'Webhook signature is invalid for provider [%s].',
                $provider,
            ),
            errors: [
                'provider' => $provider,
            ],
        );
    }
}
