<?php

namespace Equidna\StagHerd\Contracts\Gateways;

interface PayPalGateway
{
    // public function requestPayment(
    //     int $amount,
    //     string $description,
    //     ?string $returnUrl = null,
    //     ?string $cancelUrl = null,
    // ): object;

    public function getOrderDetails(string $orderId): object;

    public function getCaptureDetails(string $captureId): object;

    // public function getRefund(string $orderId, int $amount): object;

    public function captureOrder(string $orderId): object;
}
