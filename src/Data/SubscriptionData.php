<?php

namespace Equidna\StagHerd\Data;

use Carbon\CarbonImmutable;
use Equidna\StagHerd\Domain\Enums\SubscriptionStatusEnum;

final readonly class SubscriptionData
{
    /** @param array<string, mixed> $rawPayload */
    public function __construct(
        public string $provider,
        public string $id,
        public SubscriptionStatusEnum $status,
        public string $credentialContext,
        public ?string $customerId = null,
        public ?string $priceReference = null,
        public ?CarbonImmutable $currentPeriodStart = null,
        public ?CarbonImmutable $currentPeriodEnd = null,
        public bool $cancelAtPeriodEnd = false,
        public ?CarbonImmutable $canceledAt = null,
        public array $rawPayload = [],
    ) {
        //
    }
}
