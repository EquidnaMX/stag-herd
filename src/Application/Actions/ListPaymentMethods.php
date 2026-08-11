<?php

namespace Equidna\StagHerd\Application\Actions;

use Equidna\StagHerd\Contracts\ManagesPaymentMethods;
use Equidna\StagHerd\Data\PaymentMethodData;
use Equidna\StagHerd\Data\PaymentMethodsListData;

final readonly class ListPaymentMethods
{
    public function __construct(
        private ManagesPaymentMethods $paymentMethods,
    ) {
        //
    }

    /**
     * @return array<int, PaymentMethodData>
     */
    public function handle(
        PaymentMethodsListData $request
    ): array {
        return $this->paymentMethods->listPaymentMethods($request);
    }
}
