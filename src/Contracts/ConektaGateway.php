<?php

namespace Equidna\StagHerd\Contracts;

interface ConektaGateway
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function requestPayment(
        float $amount,
        string $description,
        string $customerEmail,
        ?string $customerName = null,
        array $metadata = [],
        string $paymentMethodType = 'oxxo_cash',
        ?string $tokenId = null,
    ): object;

    public function getOrderDetails(string $orderId): object;
}
