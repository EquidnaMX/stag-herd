<?php

namespace Equidna\StagHerd\Domain\Enums;

enum CheckoutStatusEnum: string
{
    case OPEN = 'open';
    case COMPLETE = 'complete';
    case EXPIRED = 'expired';
}
