<?php

namespace Equidna\StagHerd\Exceptions;

use Equidna\Toolkit\Exceptions\UnprocessableEntityException;

class InvalidPaymentPayloadException extends UnprocessableEntityException
{
    public static function missingField(string $field): self
    {
        return new self(
            message: sprintf(
                'Payment payload is missing required field [%s].',
                $field,
            ),
            errors: [
                'field' => $field,
            ],
        );
    }

    public static function invalidAmount(int|float|string|null $amount): self
    {
        return new self(
            message: 'Payment amount is invalid.',
            errors: [
                'amount' => $amount,
            ],
        );
    }

    public static function invalidCurrency(string $currency): self
    {
        return new self(
            message: sprintf(
                'Payment currency [%s] is invalid.',
                $currency,
            ),
            errors: [
                'currency' => $currency,
            ],
        );
    }

    public static function invalidMethod(
        string $provider,
        string $method,
    ): self {
        return new self(
            message: sprintf(
                'Payment method [%s] is not valid for provider [%s].',
                $method,
                $provider,
            ),
            errors: [
                'provider' => $provider,
                'method' => $method,
            ],
        );
    }

    public static function invalidField(string $field, string $message): self
    {
        return new self("Invalid field [{$field}]: {$message}");
    }
}
