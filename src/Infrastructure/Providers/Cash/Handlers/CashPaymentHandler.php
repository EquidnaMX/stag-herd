<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Cash\Handlers;

use Equidna\StagHerd\Contracts\PaymentMethodHandler;
use Equidna\StagHerd\Data\NextActionData;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\ProviderReferencesData;
use Equidna\StagHerd\Data\RefundRequestData;
use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;
use Equidna\StagHerd\Exceptions\UnsupportedOperationException;
use Equidna\StagHerd\Infrastructure\Providers\Cash\CashStatusMapper;

final class CashPaymentHandler implements PaymentMethodHandler
{
    public function __construct(
        private readonly CashStatusMapper $statusMapper = new CashStatusMapper(),
    ) {
        //
    }

    public function getMethod(): string
    {
        return 'cash';
    }

    public function createPayment(PaymentRequestData $request): PaymentResultData
    {
        $providerStatus = (string) ($request->metadata['cash_status'] ?? 'approved');

        return new PaymentResultData(
            provider: 'cash',
            method: $this->getMethod(),
            status: $this->statusMapper->map($providerStatus),
            providerStatus: $providerStatus,
            references: new ProviderReferencesData(
                providerPaymentId: $this->generateProviderPaymentId(),
            ),
            amount: $request->amount,
            currency: $request->currency,
            nextAction: NextActionData::none(),
            metadata: $request->metadata,
            rawPayload: [
                'provider' => 'cash',
                'method' => $this->getMethod(),
                'status' => $providerStatus,
                'external_reference' => $request->externalReference,
            ],
        );
    }

    public function confirmPayment(PaymentConfirmationData $request): PaymentResultData
    {
        return new PaymentResultData(
            provider: 'cash',
            method: $this->getMethod(),
            status: PaymentStatusEnum::APPROVED,
            providerStatus: 'approved',
            references: new ProviderReferencesData(
                providerPaymentId: $request->providerPaymentId,
            ),
            nextAction: NextActionData::none(),
            reason: $request->reason,
            metadata: [
                'source' => 'cash_confirm',
                'external_reference' => $request->externalReference,
            ],
        );
    }

    public function lookupPayment(PaymentLookupData $request): PaymentResultData
    {
        throw UnsupportedOperationException::forOperation(
            'lookup',
            'Cash method does not support remote lookup.'
        );
    }

    public function cancelPayment(PaymentCancellationData $request): PaymentResultData
    {
        return new PaymentResultData(
            provider: 'cash',
            method: $this->getMethod(),
            status: PaymentStatusEnum::CANCELED,
            providerStatus: 'canceled',
            references: new ProviderReferencesData(
                providerPaymentId: $request->providerPaymentId,
            ),
            nextAction: NextActionData::none(),
            reason: $request->reason,
            metadata: [
                'source' => 'cash_cancel',
                'external_reference' => $request->externalReference,
            ],
        );
    }

    public function refundPayment(RefundRequestData $request): PaymentResultData
    {
        return new PaymentResultData(
            provider: 'cash',
            method: $this->getMethod(),
            status: PaymentStatusEnum::REFUNDED,
            providerStatus: 'refunded',
            references: new ProviderReferencesData(
                providerPaymentId: $request->providerPaymentId,
                providerRefundId: 'cash_refund_' . str_replace('.', '', uniqid('', true)),
            ),
            amount: $request->amount,
            nextAction: NextActionData::none(),
            reason: $request->reason,
            metadata: [
                'source' => 'cash_refund',
                'external_reference' => $request->externalReference,
            ],
        );
    }

    private function generateProviderPaymentId(): string
    {
        return 'cash_' . str_replace('.', '', uniqid('', true));
    }
}
