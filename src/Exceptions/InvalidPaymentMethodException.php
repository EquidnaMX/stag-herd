<?php

namespace Equidna\StagHerd\Exceptions;

use Equidna\Toolkit\Exceptions\NotFoundException;

class InvalidPaymentMethodException extends NotFoundException
{
    public static function forMethod(string $method): self
    {
        return new self(
            message: sprintf(
                'Custom payment handler for method [%s] is not registered.',
                $method,
            ),
            errors: [
                'method' => $method,
            ],
        );
    }
}
