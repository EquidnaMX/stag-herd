<?php

namespace Equidna\StagHerd\Exceptions;

use Equidna\Toolkit\Exceptions\BadRequestException;

class UnsupportedOperationException extends BadRequestException
{
    public static function forProvider(
        string $provider,
        string $operation,
    ): self {
        return new self(
            message: sprintf(
                'Operation [%s] is not supported by provider [%s].',
                $operation,
                $provider,
            ),
            errors: [
                'provider' => $provider,
                'operation' => $operation,
            ],
        );
    }

    public static function forPaymentStatus(
        string $operation,
        string $status,
    ): self {
        return new self(
            message: sprintf(
                'Operation [%s] is not supported for payment status [%s].',
                $operation,
                $status,
            ),
            errors: [
                'operation' => $operation,
                'status' => $status,
            ],
        );
    }
}
