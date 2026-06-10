<?php

namespace Equidna\StagHerd\Contracts\Gateways;

interface PayPalGateway
{
    /**
     * Crea una order en PayPal.
     *
     * Importante:
     * Esto NO significa que el pago ya esté cobrado.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createOrder(
        array $payload,
        ?string $idempotencyKey = null,
    ): array;

    /**
     * Consulta una order de PayPal.
     *
     * @return array<string, mixed>
     */
    public function getOrder(string $orderId): array;

    /**
     * Captura una order aprobada por el cliente.
     *
     * Aquí sí ocurre el cobro.
     *
     * @return array<string, mixed>
     */
    public function captureOrder(
        string $orderId,
        ?string $idempotencyKey = null,
    ): array;

    /**
     * Consulta un capture.
     *
     * @return array<string, mixed>
     */
    public function getCapture(string $captureId): array;

    /**
     * Reembolsa un capture.
     *
     * @return array<string, mixed>
     */
    public function refundCapture(
        string $captureId,
        ?int $amount = null,
        ?string $currency = null,
        ?string $idempotencyKey = null,
    ): array;
}
