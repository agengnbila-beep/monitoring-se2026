<?php

namespace Database\Seeders;

use App\Models\Dashboard;
use App\Models\Dataset;
use App\Services\Query\QueryEngine;
use Illuminate\Database\Seeder;

class DashboardSeeder extends Seeder
{
    /**
     * Dashboard contoh dari dataset hasil DatasetSeeder.
     */
    public function run(QueryEngine $engine): void
    {
        $dataset = Dataset::where('name', 'Data Penduduk SE2026')->firstOrFail();

        $perKecamatan = $dataset->savedQueries()->create([
            'name' => 'Jumlah KRT per kecamatan',
            'config' => $engine->normalize($dataset, [
                'select' => ['kecamatan'],
                'aggregates' => [
                    ['fn' => 'count', 'column' => null, 'as' => 'jumlah_krt'],
                    ['fn' => 'sum', 'column' => 'jumlah_art', 'as' => 'total_art'],
                ],
                'group_by' => ['kecamatan'],
                'order_by' => [['column' => 'jumlah_krt', 'dir' => 'desc']],
            ]),
        ]);

        $total = $dataset->savedQueries()->create([
            'name' => 'Total KRT tersubmit',
            'config' => $engine->normalize($dataset, [
                'aggregates' => [['fn' => 'count', 'column' => null, 'as' => 'total']],
                'filters' => [['column' => 'status', 'op' => '=', 'value' => 'SUBMITTED']],
            ]),
        ]);

        $dashboard = Dashboard::create([
            'name' => 'Monitoring SE2026',
            'slug' => 'monitoring-se2026',
            'filters' => [
                ['key' => 'kecamatan', 'label' => 'Kecamatan', 'type' => 'select'],
                ['key' => 'tanggal_pendataan', 'label' => 'Periode pendataan', 'type' => 'date_range'],
            ],
        ]);

        $dashboard->widgets()->createMany([
            ['saved_query_id' => $total->id, 'title' => 'KRT tersubmit', 'chart_type' => 'kpi', 'options' => ['value' => 'total', 'format' => 'number'], 'sort_order' => 1, 'width' => 4],
            ['saved_query_id' => $perKecamatan->id, 'title' => 'KRT per kecamatan', 'chart_type' => 'bar', 'options' => ['x' => 'kecamatan', 'y' => ['jumlah_krt'], 'stacked' => false], 'sort_order' => 2, 'width' => 8],
            ['saved_query_id' => $perKecamatan->id, 'title' => 'Rincian per kecamatan', 'chart_type' => 'table', 'options' => ['columns' => ['kecamatan', 'jumlah_krt', 'total_art']], 'sort_order' => 3, 'width' => 12],
        ]);
    }
}
