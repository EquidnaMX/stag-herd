<?php

namespace Equidna\StagHerd\Contracts;

use Equidna\StagHerd\Data\BillingPriceData;
use Equidna\StagHerd\Data\BillingProductData;

interface BillingCatalogProvider
{
    /** @param array<string, scalar|null> $metadata */
    public function createProduct(
        string $credentialContext,
        string $name,
        array $metadata = [],
        ?string $idempotencyKey = null,
    ): BillingProductData;

    public function createPrice(
        string $credentialContext,
        string $productId,
        int $unitAmount,
        string $currency,
        ?string $recurringInterval = null,
        ?string $idempotencyKey = null,
    ): BillingPriceData;
}
