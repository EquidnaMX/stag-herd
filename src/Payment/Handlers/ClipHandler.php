<?php

/**
 * Payment handler for Clip transactions.
 *
 * Manages Clip payment requests, validations, and refunds using the ClipAdapter.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Handlers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Handlers;

use App\Classes\Clients\ClientNotifications;
use Equidna\StagHerd\Adapters\ClipAdapter;
use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Payment\Exceptions\InvalidPaymentMethodException;
use Equidna\StagHerd\Payment\Exceptions\PaymentDeclinedException;
use Exception;
use RuntimeException;
use stdClass;

class ClipHandler extends PaymentHandler
{
    public const PAYMENT_METHOD = \Equidna\StagHerd\Enums\PaymentMethod::CLIP->value;
    public const CFDI_PAYMENT_FORM = '04';

    private $clip_adapter;

    public function __construct(
        float $amount,
        ?stdClass $method_data = null,
        ?PayableOrder $order = null,
    ) {
        parent::__construct(
            amount: $amount,
            order: $order,
            method_data: $method_data
        );
        $this->clip_adapter = new ClipAdapter();
    }

    public function requestPayment(): \Equidna\StagHerd\Data\PaymentResult
    {
        // Don't call parent
        
        $methodId = null;
        if (
            is_object($this->method_data)
            && property_exists($this->method_data, 'payment_method_id')
        ) {
            $methodId = $this->method_data->payment_method_id;
        }

        $link = null;
        $payment_status = null;
        $reason = null;

        try {
            if ($methodId) {
                $payment_details = $this->clip_adapter->getPaymentDetails((string) $methodId);
                $payment_status = $payment_details->status ?? null;
            } else {
                throw new RuntimeException('No payment method id available');
            }
        } catch (Exception $e) {
            $payment_details = $this->clip_adapter->requestPayment(
                $this->amount,
                'Compra en moBig orden ' . ($this->order ? $this->order->getID() : '')
            );

            $link = $payment_details->payment_url ?? null;
            $methodId = $payment_details->id ?? null;
            $payment_status = $payment_details->status ?? null;

            if ($link && $this->order) {
                ClientNotifications::externalPaymentLink(
                    order: $this->order,
                    link: $link
                );
            }
        }

        $resultStatus = match ($payment_status) {
            'pending', 'paid' => 'PENDING', // paid in clip might mean approved? But logic said PENDING.
            default => 'DECLINED',
        };

        if ($resultStatus == 'DECLINED') {
            return \Equidna\StagHerd\Data\PaymentResult::declined(
                reason: 'Clip status: ' . ($payment_status ?? 'Unknown')
            );
        }

        return \Equidna\StagHerd\Data\PaymentResult::success(
            result: 'PENDING',
            method_id: (string) $methodId,
            link: $link
        );
    }

    protected function validatePayment($paymentModel): \Equidna\StagHerd\Data\PaymentResult
    {
        try {
            $clip_result = $this->clip_adapter->getPaymentDetails((string) $paymentModel->method_id);

            $amountValue = $clip_result->amount ?? null;

            if ($amountValue != $paymentModel->amount) {
                throw new InvalidPaymentMethodException('Invalid amount!');
            }

            $status = $clip_result->status ?? null;

            if ($status == 'paid') {
                 return \Equidna\StagHerd\Data\PaymentResult::success(
                     result: 'APPROVED',
                     method_id: (string) $paymentModel->method_id
                 );
            }
            
            return \Equidna\StagHerd\Data\PaymentResult::pending(
                   method_id: (string) $paymentModel->method_id,
                   reason: 'Clip Status: ' . $status
            );

        } catch (Exception $e) {
            return \Equidna\StagHerd\Data\PaymentResult::declined(
                reason: $e->getMessage()
            );
        }
    }

    public function cancelPayment($paymentModel): \Equidna\StagHerd\Data\PaymentResult
    {
        try {
            $this->clip_adapter->getRefund(
                $paymentModel->method_id,
                $paymentModel->amount
            );

            return \Equidna\StagHerd\Data\PaymentResult::canceled();
        } catch (Exception $e) {
            throw new PaymentDeclinedException($e->getMessage());
        }
    }
}
