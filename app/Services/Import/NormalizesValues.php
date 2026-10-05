<?php

namespace App\Services\Import;

use DateTimeInterface;

trait NormalizesValues
{
    /**
     * Seragamkan nilai sel dari semua format file.
     *
     * @return list<string|int|float|bool|null>
     */
    protected function normalize(array $values): array
    {
        return array_map(function (mixed $value) {
            if ($value instanceof DateTimeInterface) {
                return $value->format('H:i:s') === '00:00:00'
                    ? $value->format('Y-m-d')
                    : $value->format('Y-m-d H:i:s');
            }

            if (is_array($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            if (is_string($value)) {
                $value = trim($value);

                return $value === '' ? null : $value;
            }

            return $value;
        }, array_values($values));
    }
}
