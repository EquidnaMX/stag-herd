<?php

namespace Equidna\StagHerd\Data;

final readonly class BillingCustomerData
{
    public function __construct(
        public string $provider,
        public string $id,
        public string $credentialContext,
        public ?string $email = null,
        public ?string $name = null,
    ) {
        //
    }
}
