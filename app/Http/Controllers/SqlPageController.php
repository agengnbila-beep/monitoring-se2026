<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetColumn;
use App\Models\QueryFolder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SqlPageController extends Controller
{
    /**
     * Sub-menu SQL di Analitik: editor dengan panel kumpulan tabel dataset
     * (nama tabel ds_{id} dan kolomnya). File dan folder dikelola di Analitik.
     */
    public function index(Request $request): View
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

        return view('sql.index', [
            'datasets' => $datasets,
            'folders' => QueryFolder::options(),
            'folderId' => $request->integer('folder') ?: null,
        ]);
    }
}
