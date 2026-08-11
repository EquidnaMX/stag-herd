<?php

namespace Equidna\StagHerd\Contracts;

use Equidna\StagHerd\Data\PaymentMethodData;
use Equidna\StagHerd\Data\PaymentMethodDeactivateData;
use Equidna\StagHerd\Data\PaymentMethodLookupData;
use Equidna\StagHerd\Data\PaymentMethodRegisterData;
use Equidna\StagHerd\Data\PaymentMethodSetDefaultData;
use Equidna\StagHerd\Data\PaymentMethodsListData;

interface ManagesPaymentMethods
{
    public function registerPaymentMethod(
        PaymentMethodRegisterData $request
    ): PaymentMethodData;

    /**
     * @return array<int, PaymentMethodData>
     */
    public function listPaymentMethods(
        PaymentMethodsListData $request
    ): array;

    public function setDefaultPaymentMethod(
        PaymentMethodSetDefaultData $request
    ): PaymentMethodData;

    public function deactivatePaymentMethod(
        PaymentMethodDeactivateData $request
    ): PaymentMethodData;

    public function resolveUsablePaymentMethod(
        PaymentMethodLookupData $request
    ): PaymentMethodData;
}
