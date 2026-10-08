<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetColumn;
use Illuminate\View\View;

class SqlPageController extends Controller
{
    /**
     * Editor SQL dengan penjelajah folder. Daftar tabel dataset ikut dikirim
     * sebagai referensi nama tabel (ds_{id}) dan kolomnya.
     */
    public function index(): View
    {
        $datasets = Dataset::with('columns')
            ->where('status', 'done')
            ->whereNotNull('table_name')
            ->orderBy('name')
            ->get()
            ->map(fn (Dataset $dataset) => [
                'name' => $dataset->name,
                'table' => $dataset->table_name,
                'rows' => $dataset->row_count,
                'columns' => $dataset->columns->map(fn (DatasetColumn $column) => $column->only('name', 'type'))->all(),
            ]);

        return view('sql.index', ['datasets' => $datasets]);
    }
}
