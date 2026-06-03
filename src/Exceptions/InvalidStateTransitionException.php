<?php

namespace Equidna\StagHerd\Exceptions;

use Equidna\Toolkit\Exceptions\ConflictException;

class InvalidStateTransitionException extends ConflictException
{
    public static function fromTo(
        string $from,
        string $to,
    ): self {
        return new self(
            message: sprintf(
                'Payment cannot transition from status [%s] to status [%s].',
                $from,
                $to,
            ),
            errors: [
                'from' => $from,
                'to' => $to,
            ],
        );
    }
}
