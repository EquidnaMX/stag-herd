<?php

/**
 * Data Transfer Object for payment information.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Data
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
     * @param string|null $payment_method_id  The provider's method ID (e.g., token).
     * @param string|null $effective_date     The effective date of the payment.
     */
    public function __construct(
        public ?string $payment_method_id = null,
        public ?string $effective_date = null,
    ) {
        //
    }

    /**
     * Factory method to create an instance from mixed input.
     *
     * @param  mixed $data  Input data (array, stdClass, or null).
     * @return self         New PaymentData instance.
     */
    public static function fromMixed(mixed $data): self
    {
        if ($data instanceof self) {
            return $data;
        }

        if (is_array($data)) {
            return new self(
                payment_method_id: $data['payment_method_id'] ?? null,
                effective_date: $data['effective_date'] ?? null,
            );
        }

        if ($data instanceof stdClass) {
            return new self(
                payment_method_id: $data->payment_method_id ?? null,
                effective_date: $data->effective_date ?? null,
            );
        }

        return new self();
    }
}
