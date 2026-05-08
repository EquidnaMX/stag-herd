<?php

/**
 * Google Pay (Stripe) Payment Handler.
 *
 * Manages interactions with Google Pay payments processed via StripeAdapter.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Handlers
 *
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Handlers;

use Equidna\StagHerd\Adapters\StripeAdapter;
use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Data\PaymentData;
use Equidna\StagHerd\Data\PaymentResult;
use Equidna\StagHerd\Enums\PaymentMethod;
use Equidna\StagHerd\Enums\PaymentStatus;
use Equidna\StagHerd\Payment\Exceptions\InvalidPaymentMethodException;
use Equidna\StagHerd\Payment\Exceptions\PaymentDeclinedException;
use Equidna\StagHerd\Payment\Payment;
use Equidna\StagHerd\Support\WebhookVerifier;
use Equidna\Toolkit\Exceptions\BadRequestException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Handles Google Pay specific payment logic.
 */
class GooglePayHandler extends PaymentHandler
{
    public const PAYMENT_METHOD = PaymentMethod::GOOGLEPAY->value;

    public const CFDI_PAYMENT_FORM = '04';

    private StripeAdapter $stripe_adapter;

    /**
     * Creates a new GooglePayHandler instance.
     *
     * @param float             $amount      Payment amount.
     * @param PayableOrder|null $order       Order context.
     * @param PaymentData|null  $method_data Method specific data.
     */
    public function __construct(
        float $amount,
        ?PayableOrder $order = null,
        ?PaymentData $method_data = null,
    ) {
        parent::__construct(
            amount: $amount,
            order: $order,
            method_data: $method_data,
        );
        $this->stripe_adapter = new StripeAdapter();
    }

    /**
     * Requests payment.
     *
     * @return PaymentResult
     */
    public function requestPayment(): PaymentResult
    {
        $methodId = $this->getMethodData('payment_method_id');

        try {
            if ($methodId) {
                $payment_details = $this->stripe_adapter->getPaymentDetails((string) $methodId);
                // $payment_status = $payment_details->status ?? null;
            } else {
                throw new BadRequestException('No payment method id available');
            }
        } catch (Exception $e) {
            // Original code swallowed exception and returned PENDING implies flow continues
        }

        return PaymentResult::pending(
            method_id: (string) ($methodId ?? Str::random(20)),
        );
    }

    /**
     * Validates payment result.
     *
     * @param object $paymentModel
     *
     * @return PaymentResult
     */
    protected function validatePayment(object $paymentModel): PaymentResult
    {
        try {
            $stripe_result = $this->stripe_adapter->getPaymentDetails((string) $paymentModel->method_id);

            $amountRaw = $stripe_result->amount_received
                ?? $stripe_result->amount
                ?? 0;
            $amountValue = $amountRaw / 100;

            if ($amountValue != $paymentModel->amount) {
                throw new InvalidPaymentMethodException('Invalid amount!');
            }

            $status = $stripe_result->status ?? null;

            if ($status === 'succeeded') {
                return PaymentResult::success(
                    result: 'APPROVED',
                    method_id: (string) $paymentModel->method_id,
                );
            }

            return PaymentResult::pending(
                method_id: (string) $paymentModel->method_id,
                reason: 'Stripe Status: ' . $status,
            );
        } catch (Exception $e) {
            return PaymentResult::declined($e->getMessage());
        }
    }

    /**
     * Cancels payment (refunds).
     *
     * @param object $paymentModel
     *
     * @return PaymentResult
     */
    public function cancelPayment(object $paymentModel): PaymentResult
    {
        try {
            $this->stripe_adapter->getRefund($paymentModel->method_id);

            return PaymentResult::canceled();
        } catch (Exception $e) {
            throw new PaymentDeclinedException($e->getMessage());
        }
    }

    /**
     * Verifies webhook signature.
     *
     * @param Request $request
     *
     * @return array{valid: bool, reason?: string, eventId?: string|null}
     */
    public static function verifyWebhook(Request $request): array
    {
        $secret = config('stag-herd.stripe.webhook_secret');
        $tolerance = (int) config('stag-herd.stripe.tolerance', 300);

        return WebhookVerifier::verifyStripeSignature($request, (string) $secret, $tolerance);
    }

    /**
     * Processes validated webhook event.
     *
     * @param Request $request
     *
     * @return void
     */
    public static function processWebhook(Request $request): void
    {
        $event = json_decode($request->getContent(), true) ?: [];
        $type = (string) ($event['type'] ?? '');
        $object = $event['data']['object'] ?? [];

        $result = null;
        $candidates = [];

        switch ($type) {
            case 'payment_intent.succeeded':
                $candidates = [
                    $object['id'] ?? null,
                    $object['latest_charge'] ?? null,
                    $object['charges']['data'][0]['id'] ?? null,
                ];
                $result = PaymentStatus::APPROVED->value;

                break;
            case 'payment_intent.payment_failed':
                $candidates = [
                    $object['id'] ?? null,
                    $object['latest_charge'] ?? null,
                ];
                $result = PaymentStatus::REJECTED->value;

                break;
            case 'payment_intent.canceled':
                $candidates = [
                    $object['id'] ?? null,
                    $object['latest_charge'] ?? null,
                ];
                $result = PaymentStatus::CANCELED->value;

                break;
            case 'payment_intent.processing':
            case 'payment_intent.requires_action':
                $candidates = [
                    $object['id'] ?? null,
                    $object['latest_charge'] ?? null,
                ];
                $result = PaymentStatus::PENDING->value;

                break;
            case 'charge.succeeded':
                $candidates = [
                    $object['id'] ?? null,
                ];
                $result = PaymentStatus::APPROVED->value;

                break;
            case 'charge.failed':
                $candidates = [
                    $object['id'] ?? null,
                ];
                $result = PaymentStatus::REJECTED->value;

                break;
            case 'charge.pending':
                $candidates = [
                    $object['id'] ?? null,
                ];
                $result = PaymentStatus::PENDING->value;

                break;
            case 'charge.refunded':
            case 'charge.refund.updated':
                $candidates = [
                    $object['id'] ?? null,
                ];
                $result = PaymentStatus::REFUNDED->value;

                break;
            case 'charge.dispute.created':
            case 'charge.dispute.updated':
            case 'charge.dispute.funds_withdrawn':
            case 'charge.dispute.funds_reinstated':
                $candidates = [
                    $object['charge'] ?? null,
                    $object['id'] ?? null,
                ];
                $result = PaymentStatus::CHARGEBACK->value;

                break;
            default:
                return;
        }

        $payment = self::resolvePayment($candidates);
        if (is_null($payment)) {
            return;
        }

        $paymentResult = match ($result) {
            PaymentStatus::APPROVED->value => PaymentResult::success(
                result: PaymentStatus::APPROVED->value,
                method_id: (string) $payment->getMethodId(),
            ),
            PaymentStatus::REJECTED->value => new PaymentResult(
                error: true,
                result: PaymentStatus::REJECTED->value,
                reason: 'Stripe event: ' . $type,
            ),
            PaymentStatus::CANCELED->value => PaymentResult::canceled('Stripe canceled'),
            PaymentStatus::REFUNDED->value => PaymentResult::refunded('Stripe refunded'),
            PaymentStatus::CHARGEBACK->value => PaymentResult::chargeback('Stripe dispute'),
            default => PaymentResult::pending(
                method_id: (string) $payment->getMethodId(),
                reason: 'Stripe event: ' . $type,
            ),
        };

        $payment->applyResult($paymentResult);
    }

    /**
     * Attempts to resolve a payment using a list of candidate method IDs.
     *
     * @param array<int, mixed> $candidates
     *
     * @return Payment|null
     */
    private static function resolvePayment(array $candidates): ?Payment
    {
        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            try {
                return Payment::fromMethodID('GOOGLEPAY', $candidate);
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }
}
