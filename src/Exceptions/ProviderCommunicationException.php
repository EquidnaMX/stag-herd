<?php

namespace Equidna\StagHerd\Exceptions;

use Equidna\Toolkit\Exceptions\BadRequestException;

class ProviderCommunicationException extends BadRequestException
{
    public static function requestFailed(
        string $provider,
        int $status,
        array $response = [],
    ): self {
        return new self(
            message: sprintf(
                'Payment provider [%s] request failed with status [%s].',
                $provider,
                $status,
            ),
            errors: [
                'provider' => $provider,
                'status' => $status,
                'response' => $response,
            ],
        );
    }

    public static function connectionFailed(
        string $provider,
        string $reason,
    ): self {
        return new self(
            message: sprintf(
                'Could not connect to payment provider [%s].',
                $provider,
            ),
            errors: [
                'provider' => $provider,
                'reason' => $reason,
            ],
        );
    }

    public static function invalidResponse(
        string $provider,
        array $response = [],
    ): self {
        return new self(
            message: sprintf(
                'Payment provider [%s] returned an invalid response.',
                $provider,
            ),
            errors: [
                'provider' => $provider,
                'response' => $response,
            ],
        );
    }
}
