<?php

namespace Equidna\StagHerd\Support;

use stdClass;

class PaymentMethodDataNormalizer
{
    public static function normalize(mixed $data): ?object
    {
        if ($data === null) {
            return null;
        }

        if ($data instanceof stdClass) {
            return $data;
        }

        if (is_object($data)) {
            return $data;
        }

        if (is_array($data)) {
            return json_decode(json_encode($data));
        }

        if (is_string($data)) {
            $trimmed = trim($data);

            if ($trimmed === '') {
                return null;
            }

            $decoded = json_decode($data);

            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_object($decoded)) {
                    return $decoded;
                }

                if (is_array($decoded)) {
                    return json_decode(json_encode($decoded));
                }

                if ($decoded === null && strtolower($trimmed) === 'null') {
                    return null;
                }
            }

            return (object) ['payment_method_id' => $data];
        }

        if (is_int($data) || is_float($data) || is_bool($data)) {
            return (object) ['payment_method_id' => (string) $data];
        }

        return null;
    }
}
