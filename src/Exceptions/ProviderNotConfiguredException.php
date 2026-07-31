<?php

namespace Equidna\StagHerd\Exceptions;

use RuntimeException;

class ProviderNotConfiguredException extends RuntimeException
{
    public static function missingCredential(
        string $provider,
        string $credential,
    ): self {
        return new self(
            sprintf(
                'Payment provider [%s] is missing required credential [%s].',
                $provider,
                $credential,
            )
        );
    }

    public static function disabled(string $provider): self
    {
        return new self(
            sprintf(
                'Payment provider [%s] is disabled.',
                $provider,
            )
        );
    }

    public static function missingConfig(
        string $provider,
        string $configKey,
    ): self {
        return new self(
            sprintf(
                'Payment provider [%s] is missing required config [%s].',
                $provider,
                $configKey,
            )
        );
    }
}
