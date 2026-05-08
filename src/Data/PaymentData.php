<?php

/**
 * Data Transfer Object for payment information.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Data
 *
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Data;

use stdClass;

/**
 * Encapsulates data related to a payment request or response.
 */
class PaymentData
{
    /**
     * Creates a new PaymentData instance.
     *
     * @param string|null $payment_method_id The provider's method ID (e.g., token).
     * @param string|null $effective_date    The effective date of the payment.
     * @param array<string, mixed> $extra             Additional provider-specific fields.
     */
    public function __construct(
        public ?string $payment_method_id = null,
        public ?string $effective_date = null,
        public ?string $return_url = null,
        public ?string $cancel_url = null,
        public ?string $refund_id = null,
        public array $extra = [],
    ) {
        //
    }

    /**
     * Factory method to create an instance from mixed input.
     *
     * @param mixed $data Input data (array, stdClass, or null).
     *
     * @return self New PaymentData instance.
     */
    public static function fromMixed(mixed $data): self
    {
        if ($data instanceof self) {
            return $data;
        }

        $knownKeys = [
            'payment_method_id' => true,
            'effective_date' => true,
            'return_url' => true,
            'cancel_url' => true,
            'refund_id' => true,
        ];

        if (is_array($data)) {
            $extra = array_diff_key($data, $knownKeys);

            return new self(
                payment_method_id: $data['payment_method_id'] ?? null,
                effective_date: $data['effective_date'] ?? null,
                return_url: $data['return_url'] ?? null,
                cancel_url: $data['cancel_url'] ?? null,
                refund_id: $data['refund_id'] ?? null,
                extra: $extra,
            );
        }

        if ($data instanceof stdClass) {
            $raw = get_object_vars($data);
            $extra = array_diff_key($raw, $knownKeys);

            return new self(
                payment_method_id: $data->payment_method_id ?? null,
                effective_date: $data->effective_date ?? null,
                return_url: $data->return_url ?? null,
                cancel_url: $data->cancel_url ?? null,
                refund_id: $data->refund_id ?? null,
                extra: $extra,
            );
        }

        return new self();
    }

    /**
     * Gets a value from known fields or extra data.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (property_exists($this, $key)) {
            return $this->{$key} ?? $default;
        }

        return $this->extra[$key] ?? $default;
    }

    /**
     * Checks if a value exists in known fields or extra data.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        if (property_exists($this, $key)) {
            return $this->{$key} !== null;
        }

        return array_key_exists($key, $this->extra);
    }
}
