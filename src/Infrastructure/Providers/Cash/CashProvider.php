<?php

namespace Equidna\StagHerd\Infrastructure\Providers\Cash;

use Equidna\StagHerd\Contracts\PaymentProvider;
use Equidna\StagHerd\Data\NextActionData;
use Equidna\StagHerd\Data\PaymentRequestData;
use Equidna\StagHerd\Data\PaymentResultData;
use Equidna\StagHerd\Data\ProviderReferencesData;
use Equidna\StagHerd\Data\PaymentCancellationData;
use Equidna\StagHerd\Data\PaymentConfirmationData;
use Equidna\StagHerd\Data\PaymentLookupData;
use Equidna\StagHerd\Data\RefundRequestData;

final class CashProvider implements PaymentProvider
{
    public function __construct(
        private readonly CashStatusMapper $statusMapper = new CashStatusMapper(),
    ) {
        //
    }

    /**
     * Get the provider name.
     */
    public function getName(): string
    {
        return 'cash';
    }

    /**
     * Create a cash payment and return a normalized result.
     */
    public function createPayment(PaymentRequestData $request): PaymentResultData
    {
        $providerStatus = $this->resolveProviderStatus($request);

        return new PaymentResultData(
            provider: $this->getName(),
            method: $request->method,
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
                'provider' => $this->getName(),
                'method' => $request->method,
                'status' => $providerStatus,
                'external_reference' => $request->externalReference,
            ],
        );
    }

    public function lookupPayment(PaymentLookupData $request): PaymentResultData
    {
        return PaymentResultData::pending(
            provider: $this->getName(),
            method: 'cash',
            providerStatus: 'pending',
            references: new ProviderReferencesData(
                providerPaymentId: $request->providerPaymentId,
            ),
            metadata: [
                'source' => 'cash_lookup',
                'external_reference' => $request->externalReference,
            ],
        );
    }

    public function confirmPayment(PaymentConfirmationData $request): PaymentResultData
    {
        return PaymentResultData::approved(
            provider: $this->getName(),
            method: 'cash',
            providerStatus: 'approved',
            references: new ProviderReferencesData(
                providerPaymentId: $request->providerPaymentId,
            ),
            metadata: [
                'source' => 'cash_confirm',
                'external_reference' => $request->externalReference,
            ],
        );
    }

    public function cancelPayment(PaymentCancellationData $request): PaymentResultData
    {
        return new PaymentResultData(
            provider: $this->getName(),
            method: 'cash',
            status: \Equidna\StagHerd\Domain\Enums\PaymentStatusEnum::CANCELED,
            providerStatus: 'canceled',
            references: new ProviderReferencesData(
                providerPaymentId: $request->providerPaymentId,
            ),
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
            provider: $this->getName(),
            method: 'cash',
            status: \Equidna\StagHerd\Domain\Enums\PaymentStatusEnum::REFUNDED,
            providerStatus: 'refunded',
            references: new ProviderReferencesData(
                providerPaymentId: $request->providerPaymentId,
                providerRefundId: 'cash_refund_' . str_replace('.', '', uniqid('', true)),
            ),
            amount: $request->amount,
            reason: $request->reason,
            metadata: [
                'source' => 'cash_refund',
                'external_reference' => $request->externalReference,
            ],
        );
    }

    /**
     * Resolve the dummy cash status.
     */
    private function resolveProviderStatus(PaymentRequestData $request): string
    {
        return (string) ($request->metadata['cash_status'] ?? 'approved');
    }

    /**
     * Generate a dummy provider payment ID.
     */
    private function generateProviderPaymentId(): string
    {
        return 'cash_' . str_replace('.', '', uniqid('', true));
    }
}
