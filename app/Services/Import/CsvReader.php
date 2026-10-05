<?php

namespace App\Services\Import;

use Generator;
use OpenSpout\Reader\CSV\Options;
use OpenSpout\Reader\CSV\Reader;

class CsvReader implements RowReader
{
    use NormalizesValues;

    public function __construct(private string $path) {}

    public function rows(): Generator
    {
        $sample = $this->sample();

        $reader = new Reader(new Options(
            FIELD_DELIMITER: $this->detectDelimiter($sample),
            ENCODING: $this->detectEncoding($sample),
        ));

        $reader->open($this->path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    yield $this->normalize($row->toArray());
                }
            }
        } finally {
            $reader->close();
        }
    }

    public function detectDelimiter(string $sample): string
    {
        $header = strtok($sample, "\r\n") ?: '';

        $counts = [];
        foreach ([',', ';', "\t", '|'] as $delimiter) {
            $counts[$delimiter] = substr_count($header, $delimiter);
        }

        arsort($counts);

        return reset($counts) > 0 ? array_key_first($counts) : ',';
    }

    public function detectEncoding(string $sample): string
    {
        return mb_check_encoding($sample, 'UTF-8') ? 'UTF-8' : 'CP1252';
    }

    /**
     * Ambil ±64 KB pertama, dipotong di akhir baris agar karakter
     * multi-byte UTF-8 tidak terbelah.
     */
    private function sample(): string
    {
        $handle = fopen($this->path, 'r');
        $sample = (string) fread($handle, 65536);
        fclose($handle);

        $lastNewline = strrpos($sample, "\n");

        return $lastNewline === false ? $sample : substr($sample, 0, $lastNewline);
    }
}
