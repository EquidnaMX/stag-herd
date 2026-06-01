<?php

namespace Equidna\StagHerd\Domain\Enums;

enum PaymentStatusEnum: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CANCELED = 'CANCELED';
    case REFUNDED = 'REFUNDED';
    case FAILED = 'FAILED';

    public function isFinal(): bool
    {
        return match ($this) {
            self::APPROVED,
            self::REJECTED,
            self::CANCELED,
            self::REFUNDED,
            self::FAILED => true,

            self::PENDING => false,
        };
    }
}
