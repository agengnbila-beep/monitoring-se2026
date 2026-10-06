<?php

namespace App\Services\Import;

use Illuminate\Support\Str;

class ColumnNamer
{
    private const MAX_LENGTH = 60;

    /**
     * Ubah header file menjadi nama kolom yang aman untuk SQL dan unik.
     *
     * @param  list<string|null>  $headers
     * @return list<string>
     */
    public static function sanitize(array $headers): array
    {
        $names = [];
        $used = [];

        foreach (array_values($headers) as $index => $header) {
            $name = Str::of((string) $header)
                ->ascii()
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '_')
                ->trim('_')
                ->limit(self::MAX_LENGTH, '')
                ->trim('_')
                ->toString();

            if ($name === '') {
                $name = 'kolom_'.($index + 1);
            } elseif (ctype_digit($name[0])) {
                $name = 'k_'.$name;
            }

            $unique = $name;
            for ($n = 2; isset($used[$unique]); $n++) {
                $unique = $name.'_'.$n;
            }

            $used[$unique] = true;
            $names[] = $unique;
        }

        return $names;
    }
}
