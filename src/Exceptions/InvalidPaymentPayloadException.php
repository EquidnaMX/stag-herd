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
        return new self(
            message: "Invalid field [{$field}]: {$message}",
            errors: [
                'field' => $field,
            ],
        );
    }

    public static function amountMissingFromProvider(
        string $provider,
        ?string $reference = null,
    ): self {
        return new self(
            message: sprintf(
                'Provider [%s] did not return a payment amount. The payment result cannot be persisted safely.',
                $provider,
            ),
            errors: [
                'provider' => $provider,
                'reference' => $reference,
            ],
        );
    }

    public static function amountMismatch(
        int $expectedAmount,
        int $providerAmount,
        string $provider,
        ?string $reference = null,
    ): self {
        return new self(
            message: sprintf(
                'Payment amount mismatch for provider [%s]. Expected [%d], provider returned [%d].',
                $provider,
                $expectedAmount,
                $providerAmount,
            ),
            errors: [
                'provider' => $provider,
                'reference' => $reference,
                'expected_amount' => $expectedAmount,
                'provider_amount' => $providerAmount,
            ],
        );
    }

    public static function currencyMismatch(
        string $expectedCurrency,
        string $providerCurrency,
        string $provider,
        ?string $reference = null,
    ): self {
        return new self(
            message: sprintf(
                'Payment currency mismatch for provider [%s]. Expected [%s], provider returned [%s].',
                $provider,
                strtoupper($expectedCurrency),
                strtoupper($providerCurrency),
            ),
            errors: [
                'provider' => $provider,
                'reference' => $reference,
                'expected_currency' => strtoupper($expectedCurrency),
                'provider_currency' => strtoupper($providerCurrency),
            ],
        );
    }
}
