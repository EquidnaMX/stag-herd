<?php

namespace Equidna\StagHerd\Facades;

use Illuminate\Support\Facades\Facade;

class StagHerd extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Equidna\StagHerd\Application\PaymentService::class;
    }
}
