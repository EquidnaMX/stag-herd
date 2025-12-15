<?php

/**
 * Payment handler for Conekta (OXXO) transactions.
 *
 * Manages Conekta payment requests and validations using the ConektaAdapter.
 *
 * PHP 8.1+
 *
 * @package   Equidna\StagHerd\Payment\Handlers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\StagHerd\Payment\Handlers;

use App\Classes\Clients\ClientNotifications;
use Equidna\StagHerd\Adapters\ConektaAdapter;
use Equidna\StagHerd\Contracts\PayableOrder;
use Equidna\StagHerd\Payment\Exceptions\PaymentDeclinedException;
use Exception;
use stdClass;

class ConektaHandler extends PaymentHandler
{
    public const PAYMENT_METHOD = \Equidna\StagHerd\Enums\PaymentMethod::CONEKTA->value;

    public const CFDI_PAYMENT_FORM = '01';

    private ConektaAdapter $conekta_adapter;

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
        $this->conekta_adapter = new ConektaAdapter();
    }

    public function requestPayment(): \Equidna\StagHerd\Data\PaymentResult
    {
        // Don't call parent

        $methodId = null;
        $link = null;
        $result = 'PENDING';
        $reason = 'Always PENDING';

        try {
            $payment_details = $this->conekta_adapter->requestPayment(
                $this->amount,
                'Compra en moBig orden ' . ($this->order ? $this->order->getID() : '')
            );

            $methodId = $payment_details->id ?? null;
            $link = $payment_details->payment_url ?? null;

            if ($link && $this->order) {
                ClientNotifications::externalPaymentLink(
                    order: $this->order,
                    link: $link
                );
            }
        } catch (Exception $e) {
            $result = 'DECLINED';
            $reason = $e->getMessage();
        }

        if ($result == 'DECLINED') {
            return \Equidna\StagHerd\Data\PaymentResult::declined($reason);
        }

        return \Equidna\StagHerd\Data\PaymentResult::success(
            result: 'PENDING',
            method_id: (string) $methodId,
            link: $link
        );
    }

    protected function validatePayment($paymentModel): \Equidna\StagHerd\Data\PaymentResult
    {
        // Validate is trivial in original: always APPROVED
        return \Equidna\StagHerd\Data\PaymentResult::success(
            result: 'APPROVED',
            method_id: (string) $paymentModel->method_id
        );
    }

    public function cancelPayment($paymentModel): \Equidna\StagHerd\Data\PaymentResult
    {
        throw new PaymentDeclinedException('Conekta payments cannot be cancelled');
    }
}
