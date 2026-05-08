<?php

namespace Equidna\StagHerd\Contracts;

interface MercadoPagoGateway
{
    /**
     * @param array<string, mixed> $payload
     */
    public function requestPayment(float $amount, string $description, array $payload = []): object;

    public function getPaymentDetails(string $paymentId): object;

    public function getOrderDetails(string $orderId): object;

    public function getRefund(string $paymentId, float $amount): object;
}
