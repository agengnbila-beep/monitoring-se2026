<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetColumn;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatasetOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_shows_summary_column_profile_and_rows(): void
    {
        $dataset = $this->importedDataset();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('data.show', $dataset))
            ->assertOk()
            ->assertSee('Penduduk')
            ->assertSeeInOrder(['<code>kecamatan</code>', '<code>kode</code>', '<code>jumlah</code>'], false)
            ->assertSeeInOrder(['<td>Bangli</td>', '<td>Susut</td>'], false);
    }

    public function test_overview_of_dataset_not_yet_imported_is_404(): void
    {
        $dataset = Dataset::factory()->create(['status' => 'pending']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('data.show', $dataset))
            ->assertNotFound();
    }

    public function test_viewer_cannot_open_overview(): void
    {
        $dataset = $this->importedDataset();

        $this->actingAs(User::factory()->create())
            ->get(route('data.show', $dataset))
            ->assertForbidden();
    }

    public function test_label_change_is_saved(): void
    {
        $dataset = $this->importedDataset();
        $column = $this->column($dataset, 'kecamatan');

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('data.columns.update', $dataset), [
                'columns' => [$column->id => ['label' => 'Nama Kecamatan', 'type' => 'text']],
            ])
            ->assertRedirect(route('data.show', $dataset))
            ->assertSessionHas('success', 'Perubahan kolom disimpan.');

        $this->assertSame('Nama Kecamatan', $column->refresh()->label);
    }

    public function test_type_change_converts_stored_values_and_reprofiles(): void
    {
        $dataset = $this->importedDataset();
        $column = $this->column($dataset, 'kode');

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('data.columns.update', $dataset), [
                'columns' => [$column->id => ['label' => 'Kode', 'type' => 'integer']],
            ])
            ->assertRedirect(route('data.show', $dataset));

        $column->refresh();
        $this->assertSame('integer', $column->type);
        $this->assertSame('10', $column->max_value);
        $this->assertSame(
            [10, 9],
            DB::table($dataset->table_name)->orderBy('_row_id')->pluck('kode')->all(),
        );
        $this->assertSame(10, DB::table($dataset->table_name)->max('kode'));
    }

    public function test_type_change_is_rejected_when_a_value_does_not_fit(): void
    {
        $dataset = $this->importedDataset();
        $column = $this->column($dataset, 'kecamatan');

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('data.show', $dataset))
            ->patch(route('data.columns.update', $dataset), [
                'columns' => [$column->id => ['label' => 'Kecamatan', 'type' => 'integer']],
            ])
            ->assertSessionHasErrors([
                "columns.{$column->id}.type" => 'Kolom "Kecamatan" tidak bisa dijadikan integer karena berisi nilai "Bangli".',
            ]);

        $this->assertSame('text', $column->refresh()->type);
    }

    public function test_columns_of_another_dataset_cannot_be_changed(): void
    {
        $dataset = $this->importedDataset();
        $otherColumn = DatasetColumn::factory()->create(['label' => 'Asli']);

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('data.columns.update', $dataset), [
                'columns' => [$otherColumn->id => ['label' => 'Diubah', 'type' => 'text']],
            ]);

        $this->assertSame('Asli', $otherColumn->refresh()->label);
    }

    public function test_empty_label_is_rejected(): void
    {
        $dataset = $this->importedDataset();
        $column = $this->column($dataset, 'kecamatan');

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('data.columns.update', $dataset), [
                'columns' => [$column->id => ['label' => '', 'type' => 'text']],
            ])
            ->assertSessionHasErrors(["columns.{$column->id}.label" => 'Label kolom tidak boleh kosong.']);
    }

    /**
     * Tabel data dibuat langsung agar kolom "kode" tersimpan sebagai TEXT
     * walau isinya angka, seperti data yang tipenya keliru saat diimpor.
     */
    private function importedDataset(): Dataset
    {
        $dataset = Dataset::factory()->create([
            'name' => 'Penduduk',
            'table_name' => 'ds_test',
            'row_count' => 2,
            'column_count' => 3,
        ]);

        Schema::create('ds_test', function (Blueprint $table) {
            $table->id('_row_id');
            $table->text('kecamatan')->nullable();
            $table->text('kode')->nullable();
            $table->integer('jumlah')->nullable();
        });

        DB::table('ds_test')->insert([
            ['kecamatan' => 'Bangli', 'kode' => '10', 'jumlah' => 5],
            ['kecamatan' => 'Susut', 'kode' => '9', 'jumlah' => 12],
        ]);

        foreach ([['kecamatan', 'text'], ['kode', 'text'], ['jumlah', 'integer']] as $index => [$name, $type]) {
            DatasetColumn::factory()->for($dataset)->create([
                'position' => $index + 1,
                'name' => $name,
                'label' => ucfirst($name),
                'type' => $type,
            ]);
        }

        return $dataset;
    }

    private function column(Dataset $dataset, string $name): DatasetColumn
    {
        return $dataset->columns()->where('name', $name)->firstOrFail();
    }
}
