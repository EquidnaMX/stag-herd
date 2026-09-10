<?php

namespace Equidna\StagHerd\Tests\Fakes\Providers;

use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentResultData;
use RuntimeException;

final class FailingWebhookPaymentProvider extends FakeWebhookPaymentProvider
{
    public function lookupPayment(PaymentLookupData $request): PaymentResultData
    {
        throw new RuntimeException('lookup failed');
    }
}
