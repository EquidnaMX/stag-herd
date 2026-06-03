<?php

namespace Equidna\StagHerd\Exceptions;

use Equidna\Toolkit\Exceptions\UnprocessableEntityException;

class PaymentDeclinedException extends UnprocessableEntityException
{
    public static function byProvider(
        string $provider,
        ?string $providerStatus = null,
        ?string $reason = null,
    ): self {
        return new self(
            message: sprintf(
                'Payment was declined by provider [%s].',
                $provider,
            ),
            errors: [
                'provider' => $provider,
                'provider_status' => $providerStatus,
                'reason' => $reason,
            ],
        );
    }
}
