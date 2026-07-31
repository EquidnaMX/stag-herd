<?php

namespace Equidna\StagHerd\Data;

use Carbon\CarbonImmutable;
use Equidna\StagHerd\Domain\Enums\CheckoutMode;
use Equidna\StagHerd\Domain\Enums\CheckoutStatusEnum;

final readonly class CheckoutSessionData
{
    /** @param array<string, mixed> $rawPayload */
    public function __construct(
        public string $provider,
        public string $id,
        public CheckoutMode $mode,
        public CheckoutStatusEnum $status,
        public string $credentialContext,
        public ?string $url = null,
        public ?string $customerId = null,
        public ?string $subscriptionId = null,
        public ?string $paymentId = null,
        public ?string $paymentStatus = null,
        public ?string $externalReference = null,
        public ?int $amountTotal = null,
        public ?string $currency = null,
        public ?CarbonImmutable $expiresAt = null,
        public array $rawPayload = [],
    ) {
        //
    }
}
