<?php

namespace Equidna\StagHerd\Contracts;

interface ClipGateway
{
    /**
     * @param array<string, mixed> $options
     */
    public function requestPayment(float $amount, string $description, array $options = []): object;

    public function getPaymentDetails(string $paymentId): object;

    public function getRefund(string $paymentId, float $amount): object;
}
