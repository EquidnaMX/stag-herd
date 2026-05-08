<?php

namespace Equidna\StagHerd\Contracts;

interface PayPalGateway
{
    public function requestPayment(
        float $amount,
        string $description,
        ?string $returnUrl = null,
        ?string $cancelUrl = null,
    ): object;

    public function getOrderDetails(string $orderId): object;

    public function getRefund(string $orderId, float $amount): object;

    public function captureOrder(string $orderId): object;
}
