<?php

namespace Equidna\StagHerd\Payment;

use Equidna\StagHerd\Payment\Payment as PaymentDomain;

final class PaymentFactory
{
    public static function fromMethodID(string $method, string $methodId)
    {
        return PaymentDomain::fromMethodID($method, $methodId);
    }
}
