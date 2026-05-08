<?php

namespace Equidna\StagHerd\Contracts;

interface OpenpayGateway
{
    public function createBankCharge(
        float $amount,
        string $description,
        string $customerName,
        string $customerEmail,
    ): object;

    public function getChargeDetails(string $chargeId): object;

    public function getRefund(string $chargeId, float $amount): object;
}
