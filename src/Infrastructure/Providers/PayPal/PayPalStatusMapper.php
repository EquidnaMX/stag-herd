<?php

namespace Equidna\StagHerd\Infrastructure\Providers\PayPal;

use Equidna\StagHerd\Domain\Enums\PaymentStatusEnum;

class PayPalStatusMapper
{
    public function map(?string $status): PaymentStatusEnum
    {
        return match (strtoupper((string) $status)) {
            /*
             * Order creada, pero falta aprobación del comprador.
             */
            'CREATED',
            'PAYER_ACTION_REQUIRED',
            'SAVED',
            'APPROVED' => PaymentStatusEnum::PENDING,

            /*
             * Dinero capturado.
             */
            'COMPLETED',
            'CAPTURED' => PaymentStatusEnum::APPROVED,

            /*
             * Cancelado/anulado.
             */
            'VOIDED',
            'CANCELLED',
            'CANCELED' => PaymentStatusEnum::CANCELED,

            /*
             * Reembolso.
             */
            'REFUNDED',
            'PARTIALLY_REFUNDED' => PaymentStatusEnum::REFUNDED,

            /*
             * Estados fallidos.
             */
            'DECLINED',
            'DENIED',
            'FAILED',
            'EXPIRED' => PaymentStatusEnum::FAILED,

            default => PaymentStatusEnum::PENDING,
        };
    }
}
