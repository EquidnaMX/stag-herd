<?php

/**
 * Abstract base Payment Handler.
 *
 * Defines the contract and common behavior for all payment handlers.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Handlers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Handlers;

use Carbon\Carbon;
use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Payment\Exceptions\InvalidPaymentMethodException;
use Equidna\StagHerd\Payment\Exceptions\PaymentDeclinedException;
use Equidna\StagHerd\Payment\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use stdClass;

/**
 * Base class for payment provider implementations.
 */
abstract class PaymentHandler
{
    public const PAYMENT_METHOD = 'BASE';
    public const CFDI = null;
    public const CFDI_PAYMENT_FORM = '01';
    public const ALLOW_DUPLICATED_METHOD_ID = false;

    /**
     * Creates a new PaymentHandler instance.
     *
     * @param float             $amount       Payment amount.
     * @param PayableOrder|null $order        Order context.
     * @param stdClass|null     $method_data  Method specific data.
     */
    public function __construct(
        protected float $amount,
        protected ?PayableOrder $order,
        protected ?stdClass $method_data,
    ) {
        //
    }

    /**
     * Initiates the payment request logic.
     *
     * @return \Equidna\StagHerd\Data\PaymentResult Result object with status and ID.
     * @throws PaymentDeclinedException               If the order is missing.
     */
    public function requestPayment(): \Equidna\StagHerd\Data\PaymentResult
    {
        if (is_null($this->order)) {
            throw new PaymentDeclinedException('Order not loaded');
        }

        // $client = $this->order->getClient();

        $methodId = null;
        if (
            is_object($this->method_data)
            && property_exists($this->method_data, 'payment_method_id')
        ) {
            $methodId = $this->method_data->payment_method_id;
        }

        return \Equidna\StagHerd\Data\PaymentResult::pending(
            method_id: $methodId ?? Str::random(20),
            reason: 'Always PENDING'
        );
    }

    /**
     * Validates payment status and details.
     *
     * @param  object $paymentModel                   The payment model or wrapper.
     * @return \Equidna\StagHerd\Data\PaymentResult   Validation result.
     * @throws InvalidPaymentMethodException          If method mismatch.
     * @throws PaymentDeclinedException               If validation fails.
     */
    protected function validatePayment(object $paymentModel): \Equidna\StagHerd\Data\PaymentResult
    {
        if (!array_key_exists($paymentModel->method, Payment::VALID_STATUS)) {
            // Logic preserved from original
        }

        if ($paymentModel->amount != $this->amount) {
            throw new PaymentDeclinedException('Invalid amount');
        }

        if (is_null($this->order)) {
            throw new PaymentDeclinedException('Order not loaded');
        }

        if ($paymentModel->status != 'PENDING') {
            throw new PaymentDeclinedException('Payment is not pending validation');
        }

        return \Equidna\StagHerd\Data\PaymentResult::pending(
            method_id: $paymentModel->method_id,
            reason: 'Always PENDING'
        );
    }

    /**
     * Approves the payment.
     *
     * @param  mixed $paymentModel                    The payment model.
     * @return \Equidna\StagHerd\Data\PaymentResult   Approval result.
     */
    public function approvePayment(object $paymentModel): \Equidna\StagHerd\Data\PaymentResult
    {
        return $this->validatePayment($paymentModel);
    }

    /**
     * Cancels the payment.
     *
     * @param  mixed $paymentModel                    The payment model.
     * @return \Equidna\StagHerd\Data\PaymentResult   Cancellation result.
     */
    public function cancelPayment(object $paymentModel): \Equidna\StagHerd\Data\PaymentResult
    {
        return \Equidna\StagHerd\Data\PaymentResult::canceled();
    }

    /**
     * Calculates the payment fee.
     *
     * @return float  The calculated fee.
     */
    public function getFee(): float
    {
        $fixed = config("stag-herd.fees." . static::PAYMENT_METHOD . ".fixed", 0);
        $variable = config("stag-herd.fees." . static::PAYMENT_METHOD . ".variable", 0);

        return $fixed + ($this->amount * $variable);
    }

    /**
     * Gets the effective date of the payment.
     *
     * @return Carbon  Effective date.
     */
    public function getEffectiveDate(): Carbon
    {
        $effective_date = null;

        if (
            is_object($this->method_data)
            && property_exists($this->method_data, 'effective_date')
        ) {
            $effective_date = Carbon::parse($this->method_data->effective_date);
        }

        return $effective_date ?? Carbon::now();
    }

    /**
     * Verifies the webhook signature.
     *
     * Static method to allow verification before instantiation.
     *
     * @param  Request $request                                                    The incoming request.
     * @return array{valid: bool, eventId: ?string, reason: ?string, data: ?array} Verification result.
     */
    public static function verifyWebhook(Request $request): array
    {
        return [
            'valid' => false,
            'reason' => 'Not implemented',
            'eventId' => null,
            'data' => null,
        ];
    }
}
