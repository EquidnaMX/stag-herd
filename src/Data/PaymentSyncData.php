<?php

namespace Equidna\StagHerd\Data;

final readonly class PaymentSyncData
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $provider,

        /**
         * Tipo de referencia que tienes para buscar en el provider.
         *
         * Ejemplos:
         * - provider_payment_id
         * - provider_order_id
         * - provider_transaction_id
         * - external_reference
         */
        public string $referenceType,

        /**
         * Valor real de la referencia.
         *
         * Ejemplo Mercado Pago:
         * - 123456789 si referenceType = provider_payment_id
         * - ORDER-123 si referenceType = external_reference
         */
        public string $reference,

        /**
         * Datos opcionales para crear el pago local si no existe.
         *
         * Útil cuando el provider no devuelve todo lo que necesitas
         * para reconstruir el Payment local.
         */
        public ?string $method = null,
        public ?string $currency = null,
        public ?string $externalReference = null,
        public ?string $payerReference = null,
        public ?string $payerEmail = null,
        public ?string $description = null,
        public array $metadata = [],
    ) {
        //
    }
}
