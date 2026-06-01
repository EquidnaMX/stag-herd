<?php

namespace Equidna\StagHerd\Contracts;

interface MercadoPagoGateway
{
    /**
     * @param array<string, mixed> $payload
     */
    public function requestPayment(int $amount, string $description, array $payload = []): object;

    public function getPaymentDetails(string $paymentId): object;

    public function getOrderDetails(string $orderId): object;

    public function getRefund(string $paymentId, int $amount): object;
}
