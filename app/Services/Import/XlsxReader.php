<?php

namespace App\Services\Import;

use Generator;
use InvalidArgumentException;
use OpenSpout\Reader\XLSX\Reader;

class XlsxReader implements RowReader
{
    use NormalizesValues;

    public function __construct(private string $path, private ?string $sheet = null) {}

    /**
     * @return list<string>
     */
    public function sheetNames(): array
    {
        $reader = new Reader;
        $reader->open($this->path);

        try {
            $names = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                $names[] = $sheet->getName();
            }

            return $names;
        } finally {
            $reader->close();
        }
    }

    public function rows(): Generator
    {
        $reader = new Reader;
        $reader->open($this->path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($this->sheet !== null && $sheet->getName() !== $this->sheet) {
                    continue;
                }

                foreach ($sheet->getRowIterator() as $row) {
                    yield $this->normalize($row->toArray());
                }

                return;
            }

            throw new InvalidArgumentException("Sheet '{$this->sheet}' tidak ditemukan.");
        } finally {
            $reader->close();
        }
    }
}
