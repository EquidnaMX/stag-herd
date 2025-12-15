<?php

/**
 * Google Pay (Stripe) Payment Handler.
 *
 * Manages interactions with Google Pay payments processed via StripeAdapter.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Handlers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Handlers;

use Equidna\StagHerd\Adapters\StripeAdapter;
use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Payment\Exceptions\InvalidPaymentMethodException;
use Equidna\StagHerd\Payment\Exceptions\PaymentDeclinedException;
use Equidna\StagHerd\Payment\Payment;
use Equidna\StagHerd\Support\WebhookVerifier;
use Equidna\Toolkit\Exceptions\BadRequestException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use stdClass;

/**
 * Handles Google Pay specific payment logic.
 */
class GooglePayHandler extends PaymentHandler
{
    public const PAYMENT_METHOD = \Equidna\StagHerd\Enums\PaymentMethod::GOOGLEPAY->value;

    public const CFDI_PAYMENT_FORM = '04';

    private StripeAdapter $stripe_adapter;

    /**
     * Creates a new GooglePayHandler instance.
     *
     * @param float             $amount       Payment amount.
     * @param PayableOrder|null $order        Order context.
     * @param stdClass|null     $method_data  Method specific data.
     */
    public function __construct(
        float $amount,
        ?PayableOrder $order = null,
        ?stdClass $method_data = null,
    ) {
        parent::__construct(
            amount: $amount,
            order: $order,
            method_data: $method_data
        );
        $this->stripe_adapter = new StripeAdapter();
    }

    /**
     * Requests payment.
     *
     * @return stdClass
     */
    /**
     * Requests payment.
     *
     * @return \Equidna\StagHerd\Data\PaymentResult
     */
    public function requestPayment(): \Equidna\StagHerd\Data\PaymentResult
    {
        $methodId = null;
        if (
            is_object($this->method_data)
            && property_exists($this->method_data, 'payment_method_id')
        ) {
            $methodId = $this->method_data->payment_method_id;
        }

        try {
            if ($methodId) {
                $payment_details = $this->stripe_adapter->getChargeDetails((string) $methodId);
                // $payment_status = $payment_details->status ?? null;
            } else {
                throw new BadRequestException('No payment method id available');
            }
        } catch (Exception $e) {
            // Original code swallowed exception and returned PENDING implies flow continues
        }

        return \Equidna\StagHerd\Data\PaymentResult::pending(
            method_id: (string) ($methodId ?? Str::random(20))
        );
    }

    /**
     * Validates payment result.
     *
     * @param  object $paymentModel
     * @return \Equidna\StagHerd\Data\PaymentResult
     */
    protected function validatePayment(object $paymentModel): \Equidna\StagHerd\Data\PaymentResult
    {
        try {
            $stripe_result = $this->stripe_adapter->getChargeDetails((string) $paymentModel->method_id);

            $amountValue = ($stripe_result->amount ?? 0) / 100;

            if ($amountValue != $paymentModel->amount) {
                throw new InvalidPaymentMethodException('Invalid amount!');
            }

            $status = $stripe_result->status ?? null;

            if ($status == 'succeeded') {
                return \Equidna\StagHerd\Data\PaymentResult::success(
                    result: 'APPROVED',
                    method_id: (string) $paymentModel->method_id
                );
            }

            return \Equidna\StagHerd\Data\PaymentResult::pending(
                method_id: (string) $paymentModel->method_id,
                reason: 'Stripe Status: ' . $status
            );
        } catch (Exception $e) {
            return \Equidna\StagHerd\Data\PaymentResult::declined($e->getMessage());
        }
    }

    /**
     * Cancels payment (refunds).
     *
     * @param  object $paymentModel
     * @return \Equidna\StagHerd\Data\PaymentResult
     */
    public function cancelPayment(object $paymentModel): \Equidna\StagHerd\Data\PaymentResult
    {
        try {
            $this->stripe_adapter->getRefund($paymentModel->method_id);

            return \Equidna\StagHerd\Data\PaymentResult::canceled();
        } catch (Exception $e) {
            throw new PaymentDeclinedException($e->getMessage());
        }
    }

    /**
     * Verifies webhook signature.
     *
     * @param  Request $request
     * @return array{valid: bool, reason?: string, eventId?: string|null}
     */
    public static function verifyWebhook(Request $request): array
    {
        $secret = config('stag-herd.stripe.secret');
        $tolerance = (int) config('stag-herd.stripe.tolerance', 300);

        return WebhookVerifier::verifyStripeSignature($request, (string) $secret, $tolerance);
    }

    /**
     * Processes validated webhook event.
     *
     * @param  Request $request
     * @return void
     */
    public static function processWebhook(Request $request): void
    {
        $event = json_decode($request->getContent(), true) ?: [];
        $type = $event['type'] ?? '';

        if (!in_array($type, ['payment_intent.succeeded', 'charge.succeeded'], true)) {
            return;
        }

        $object = $event['data']['object'] ?? [];
        $methodId = $object['id'] ?? null;

        if ($methodId) {
            $payment = Payment::fromMethodID('GOOGLEPAY', (string) $methodId);
            $payment->approvePayment();
        }
    }
}
