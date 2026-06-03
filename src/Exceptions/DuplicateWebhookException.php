<?php

namespace Equidna\StagHerd\Exceptions;

use Equidna\Toolkit\Exceptions\ConflictException;

class DuplicateWebhookException extends ConflictException
{
    public static function withKey(string $idempotencyKey): self
    {
        return new self(
            message: sprintf(
                'Webhook with idempotency key [%s] was already processed.',
                $idempotencyKey,
            ),
            errors: [
                'idempotency_key' => $idempotencyKey,
            ],
        );
    }
}
