<?php

namespace Equidna\StagHerd\Tests\Fakes\PaymentMethods;

use Equidna\StagHerd\Data\PaymentMethodDeactivateData;
use Equidna\StagHerd\Data\PaymentMethodSetDefaultData;
use Equidna\StagHerd\Contracts\ManagesPaymentMethods;
use Equidna\StagHerd\Data\PaymentMethodRegisterData;
use Equidna\StagHerd\Data\PaymentMethodLookupData;
use Equidna\StagHerd\Data\PaymentMethodsListData;
use Equidna\StagHerd\Data\PaymentMethodData;
use RuntimeException;

final class NullPaymentMethodManager implements ManagesPaymentMethods
{
    public function registerPaymentMethod(PaymentMethodRegisterData $request): PaymentMethodData
    {
        throw new RuntimeException('Not implemented.');
    }

    public function listPaymentMethods(PaymentMethodsListData $request): array
    {
        throw new RuntimeException('Not implemented.');
    }

    public function setDefaultPaymentMethod(PaymentMethodSetDefaultData $request): PaymentMethodData
    {
        throw new RuntimeException('Not implemented.');
    }

    public function deactivatePaymentMethod(PaymentMethodDeactivateData $request): PaymentMethodData
    {
        throw new RuntimeException('Not implemented.');
    }

    public function resolveUsablePaymentMethod(PaymentMethodLookupData $request): PaymentMethodData
    {
        throw new RuntimeException('Not implemented.');
    }
}
