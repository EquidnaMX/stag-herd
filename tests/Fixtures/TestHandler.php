<?php

namespace Equidna\StagHerd\Tests\Fixtures;

use Equidna\StagHerd\Data\PaymentResult;
use Equidna\StagHerd\Payment\Handlers\PaymentHandler;

class TestHandler extends PaymentHandler
{
    public const PAYMENT_METHOD = 'TEST_METHOD';

    public function requestPayment(): PaymentResult
    {
        return PaymentResult::pending(
            method_id: 'test_id_' . uniqid()
        );
    }
}
