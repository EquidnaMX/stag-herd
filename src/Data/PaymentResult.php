<?php

/**
 * Payment result value object.
 *
 * Encapsulates the outcome of payment operations including status, metadata, and error information.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Data
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Data;

/**
 * Represents the result of a payment operation.
 */
class PaymentResult
{
    /**
     * Creates a new PaymentResult instance.
     *
     * @param bool                   $error      Whether an error occurred.
     * @param string                 $result     Payment status (APPROVED, PENDING, DECLINED, etc.).
     * @param string|null            $reason     Reason for the result status.
     * @param string|null            $method_id  Provider's payment method ID.
     * @param string|null            $link       Payment link if applicable.
     * @param array<string, mixed>   $metadata   Additional metadata.
     */
    public function __construct(
        public bool $error = false,
        public string $result = 'PENDING',
        public ?string $reason = null,
        public ?string $method_id = null,
        public ?string $link = null,
        public array $metadata = []
    ) {
    }

    /**
     * Creates a successful payment result.
     *
     * @param  string                $result     Payment status.
     * @param  string                $method_id  Provider's payment method ID.
     * @param  string|null           $link       Payment link if applicable.
     * @param  array<string, mixed>  $metadata   Additional metadata.
     * @return self                              New success result instance.
     */
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

    /**
     * Creates a pending payment result.
     *
     * @param  string      $method_id  Provider's payment method ID.
     * @param  string|null $link       Payment link if applicable.
     * @param  string|null $reason     Reason for pending status.
     * @return self                    New pending result instance.
     */
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

    /**
     * Creates a declined payment result.
     *
     * @param  string $reason  Reason for decline.
     * @return self            New declined result instance.
     */
    public static function declined(string $reason): self
    {
        return new self(
            error: true,
            result: 'DECLINED',
            reason: $reason
        );
    }

    /**
     * Creates a canceled payment result.
     *
     * @param  string $reason  Reason for cancellation.
     * @return self            New canceled result instance.
     */
    public static function canceled(string $reason = 'Always CANCELED'): self
    {
        return new self(
            error: false, // Cancelled is not necessarily an error
            result: 'CANCELED',
            reason: $reason
        );
    }
}
