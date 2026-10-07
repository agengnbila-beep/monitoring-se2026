<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Alur lengkap seperti yang dilakukan pengguna:
 * upload → pratinjau → import → profil → query → saved query → dashboard → viewer.
 */
class EndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_flow_from_upload_to_filtered_dashboard(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $viewer = User::factory()->create();

        // 1. Admin mengupload CSV berpemisah titik koma dan melihat pratinjau.
        $csv = "Kecamatan;Desa;Status;Jumlah ART\nBangli;Kubu;SUBMITTED;4\nBangli;Cempaga;OPEN;3\nSusut;Abuan;SUBMITTED;5\n";
        $upload = $this->actingAs($admin)->postJson('/data/upload', [
            'file' => UploadedFile::fake()->createWithContent('pendataan.csv', $csv),
        ])->assertCreated();

        $this->get($upload->json('redirect'))->assertOk()->assertSee('>Cempaga</td>', false);

        // 2. Konfirmasi menjalankan import (queue sync saat test).
        $dataset = Dataset::sole();
        $this->post(route('data.confirm', $dataset), ['name' => 'Pendataan'])->assertRedirect(route('data'));

        $dataset->refresh();
        $this->assertSame('done', $dataset->status);
        $this->assertSame(3, $dataset->row_count);

        // 3. Profil kolom tersedia di laman Overview dan API.
        $this->get(route('data.show', $dataset))->assertOk()->assertSee('<code>jumlah_art</code>', false);
        $this->getJson("/api/datasets/{$dataset->id}/profile")
            ->assertJsonPath('data.columns.3', [
                'name' => 'jumlah_art', 'label' => 'Jumlah ART', 'type' => 'integer', 'position' => 4,
                'null_count' => 0, 'unique_count' => 3, 'min' => '3', 'max' => '5',
            ]);

        // 4. Query builder: total ART per kecamatan, lalu disimpan.
        $config = [
            'select' => ['kecamatan'],
            'aggregates' => [['fn' => 'sum', 'column' => 'jumlah_art', 'as' => 'total_art']],
            'group_by' => ['kecamatan'],
            'order_by' => [['column' => 'kecamatan', 'dir' => 'asc']],
        ];
        $this->postJson('/api/queries/run', ['dataset_id' => $dataset->id, 'config' => $config])
            ->assertJsonPath('data.rows', [['kecamatan' => 'Bangli', 'total_art' => 7], ['kecamatan' => 'Susut', 'total_art' => 5]]);

        $savedQueryId = $this->postJson('/api/queries', ['dataset_id' => $dataset->id, 'name' => 'ART per kecamatan', 'config' => $config])
            ->assertCreated()
            ->json('data.id');

        // 5. Dashboard dengan filter global dan satu widget bar.
        $dashboard = $this->postJson('/api/dashboards', [
            'name' => 'Pendataan',
            'filters' => [['key' => 'status', 'label' => 'Status', 'type' => 'select']],
        ])->assertCreated()->json('data');

        $widgetId = $this->postJson("/api/dashboards/{$dashboard['id']}/widgets", [
            'saved_query_id' => $savedQueryId,
            'title' => 'ART per kecamatan',
            'chart_type' => 'bar',
            'options' => ['x' => 'kecamatan', 'y' => ['total_art']],
        ])->assertCreated()->json('data.id');

        // 6. Viewer membuka dashboard dan memfilter status SUBMITTED.
        $this->actingAs($viewer)
            ->get(route('dashboards.show', $dashboard['slug']))
            ->assertOk()
            ->assertSee('"title":"ART per kecamatan"', false);

        $this->getJson("/api/widgets/{$widgetId}/data?filters[status]=SUBMITTED")
            ->assertOk()
            ->assertJsonPath('data.rows', [['kecamatan' => 'Bangli', 'total_art' => 4], ['kecamatan' => 'Susut', 'total_art' => 5]]);

        // 7. Viewer tetap tidak bisa membuka laman Data.
        $this->get(route('data'))->assertForbidden();
    }
}
