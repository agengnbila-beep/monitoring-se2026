<?php

namespace App\Services\Import;

use Generator;
use InvalidArgumentException;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

class JsonReader implements RowReader
{
    use NormalizesValues;

    public function __construct(private string $path) {}

    public function rows(): Generator
    {
        $headers = null;

        $items = Items::fromFile($this->path, ['decoder' => new ExtJsonDecoder(true)]);

        foreach ($items as $item) {
            if (! is_array($item) || array_is_list($item)) {
                throw new InvalidArgumentException('JSON harus berupa array of objects, contoh: [{"nama": "A"}, ...].');
            }

            if ($headers === null) {
                $headers = array_map('strval', array_keys($item));

                yield $headers;
            }

            yield $this->normalize(array_map(fn (string $key) => $item[$key] ?? null, $headers));
        }
    }
}
