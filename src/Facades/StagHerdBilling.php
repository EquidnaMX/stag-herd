<?php

namespace Equidna\StagHerd\Facades;

use Equidna\StagHerd\Application\BillingService;
use Illuminate\Support\Facades\Facade;

final class StagHerdBilling extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BillingService::class;
    }
}
