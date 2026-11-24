<?php

namespace Equidna\StagHerd\Data;

/** No raw payloads or payer data: suitable for an administrative outcome. */
final readonly class CheckoutClosureResult
{
    public function __construct(
        public bool $closed,
        public string $reason,
        public bool $requiresCaptureBlock = false,
    ) {}
}
