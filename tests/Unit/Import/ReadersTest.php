<?php

namespace Tests\Unit\Import;

use App\Services\Import\CsvReader;
use App\Services\Import\JsonReader;
use App\Services\Import\ReaderFactory;
use App\Services\Import\XlsxReader;
use DateTimeImmutable;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\TestCase;

class ReadersTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_csv_with_semicolon_delimiter(): void
    {
        $path = $this->tempFile('csv', "nama;umur\nBudi;20\n\nSiti; 31 \n");

        $this->assertSame(
            [['nama', 'umur'], ['Budi', '20'], ['Siti', '31']],
            iterator_to_array((new CsvReader($path))->rows(), false),
        );
    }

    public function test_csv_in_windows_1252_is_converted_to_utf8(): void
    {
        $path = $this->tempFile('csv', mb_convert_encoding("nama,kota\nJosé,Gianyar\n", 'CP1252', 'UTF-8'));

        $rows = iterator_to_array((new CsvReader($path))->rows(), false);

        $this->assertSame('José', $rows[1][0]);
    }

    public function test_csv_byte_order_mark_is_removed(): void
    {
        $path = $this->tempFile('csv', "\xEF\xBB\xBFid,nama\n1,A\n");

        $rows = iterator_to_array((new CsvReader($path))->rows(), false);

        $this->assertSame('id', $rows[0][0]);
    }

    public function test_xlsx_lists_sheets_and_reads_selected_sheet(): void
    {
        $reader = new XlsxReader($this->makeXlsx(), 'Data');

        $this->assertSame(['Ringkasan', 'Data'], $reader->sheetNames());
        $this->assertSame(
            [['tanggal', 'nilai'], ['2026-06-01', 3.5]],
            iterator_to_array($reader->rows(), false),
        );
    }

    public function test_xlsx_unknown_sheet_is_rejected(): void
    {
        $reader = new XlsxReader($this->makeXlsx(), 'Tidak Ada');

        $this->expectException(InvalidArgumentException::class);

        iterator_to_array($reader->rows());
    }

    public function test_json_array_of_objects(): void
    {
        $path = $this->tempFile('json', '[{"nama":"Budi","detail":{"umur":20}},{"nama":"Siti"}]');

        $this->assertSame(
            [['nama', 'detail'], ['Budi', '{"umur":20}'], ['Siti', null]],
            iterator_to_array((new JsonReader($path))->rows(), false),
        );
    }

    public function test_json_that_is_not_array_of_objects_is_rejected(): void
    {
        $path = $this->tempFile('json', '[1, 2, 3]');

        $this->expectException(InvalidArgumentException::class);

        iterator_to_array((new JsonReader($path))->rows());
    }

    public function test_factory_rejects_unknown_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ReaderFactory::make('laporan.pdf', 'pdf');
    }

    private function tempFile(string $extension, string $contents = ''): string
    {
        $path = sys_get_temp_dir().'/'.uniqid('import_', true).'.'.$extension;
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }

    private function makeXlsx(): string
    {
        $path = $this->tempFile('xlsx');

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Ringkasan');
        $writer->addRow(Row::fromValues(['abaikan']));
        $writer->addNewSheetAndMakeItCurrent()->setName('Data');
        $writer->addRow(Row::fromValues(['tanggal', 'nilai']));
        $writer->addRow(Row::fromValuesWithStyles(
            [new DateTimeImmutable('2026-06-01'), 3.5],
            [0 => (new Style)->withFormat('yyyy-mm-dd')],
        ));
        $writer->close();

        return $path;
    }
}
