<?php

namespace Equidna\StagHerd\Tests\Fixtures;

use Equidna\StagHerd\Payment\Handlers\PaymentHandler;
use stdClass;

class TestHandler extends PaymentHandler
{
    public const PAYMENT_METHOD = 'TEST_METHOD';

    public function requestPayment(): stdClass
    {
        $result = parent::requestPayment();
        $result->method_id = 'test_id_' . uniqid();

        return $result;
    }
}
