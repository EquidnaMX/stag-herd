<?php

namespace Equidna\StagHerd\Data;

final readonly class BillingProductData
{
    /** @param array<string, scalar|null> $metadata */
    public function __construct(
        public string $provider,
        public string $id,
        public string $name,
        public bool $active,
        public string $credentialContext,
        public array $metadata = [],
    ) {
        //
    }
}
