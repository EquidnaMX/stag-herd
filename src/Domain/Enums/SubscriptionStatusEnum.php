<?php

namespace Equidna\StagHerd\Domain\Enums;

enum SubscriptionStatusEnum: string
{
    case INCOMPLETE = 'incomplete';
    case INCOMPLETE_EXPIRED = 'incomplete_expired';
    case TRIALING = 'trialing';
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case CANCELED = 'canceled';
    case UNPAID = 'unpaid';
    case PAUSED = 'paused';
    case UNKNOWN = 'unknown';

    public static function fromProvider(?string $status): self
    {
        return self::tryFrom(strtolower((string) $status)) ?? self::UNKNOWN;
    }
}
