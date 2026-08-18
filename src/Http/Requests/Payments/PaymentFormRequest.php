<?php

namespace Equidna\StagHerd\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

abstract class PaymentFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function normalizeNullableString(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    protected function normalizeLower(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === '' ? null : strtolower($value);
    }

    protected function normalizeUpper(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === '' ? null : strtoupper($value);
    }

    protected function cleanMetadata(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->cleanMetadata($value);

                if ($nested === []) {
                    continue;
                }

                $clean[$key] = $nested;

                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    protected function resolvedIdempotencyKey(
        ?string $value,
        string $prefix,
        int $maxLength = 255,
    ): string {
        return substr(
            (string) ($value ?: $this->header('X-Idempotency-Key') ?: $prefix . Str::uuid()),
            0,
            $maxLength,
        );
    }
}
