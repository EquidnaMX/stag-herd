<?php

namespace Equidna\StagHerd\Data;

class PaymentResult
{
    public function __construct(
        public bool $error = false,
        public string $result = 'PENDING',
        public ?string $reason = null,
        public ?string $method_id = null,
        public ?string $link = null,
        public array $metadata = []
    ) {
    }

    public static function success(
        string $result,
        string $method_id,
        ?string $link = null,
        array $metadata = []
    ): self {
        return new self(
            error: false,
            result: $result,
            method_id: $method_id,
            link: $link,
            metadata: $metadata
        );
    }

    public static function pending(
        string $method_id,
        ?string $link = null,
        ?string $reason = 'Always PENDING'
    ): self {
        return new self(
            error: false,
            result: 'PENDING',
            reason: $reason,
            method_id: $method_id,
            link: $link
        );
    }

    public static function declined(string $reason): self
    {
        return new self(
            error: true,
            result: 'DECLINED',
            reason: $reason
        );
    }

    public static function canceled(string $reason = 'Always CANCELED'): self
    {
        return new self(
            error: false, // Cancelled is not necessarily an error
            result: 'CANCELED',
            reason: $reason
        );
    }
}
