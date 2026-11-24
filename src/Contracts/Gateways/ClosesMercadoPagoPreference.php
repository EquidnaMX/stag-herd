<?php

namespace Equidna\StagHerd\Contracts\Gateways;

interface ClosesMercadoPagoPreference
{
    /** @return array<string, mixed> */
    public function getPreference(string $preferenceId): array;

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updatePreference(string $preferenceId, array $payload): array;
}
