<?php

namespace App\Http\Controllers;

use App\Http\Resources\SavedQueryResource;
use App\Models\Dataset;
use App\Models\QueryFolder;
use App\Models\SavedQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QueryPageController extends Controller
{
    /**
     * Query builder. Dengan `?query={id}` builder membuka saved query untuk diedit.
     */
    public function builder(Request $request): View|RedirectResponse
    {
        $savedQuery = $request->filled('query')
            ? SavedQuery::with('dataset:id,name')->findOrFail($request->integer('query'))
            : null;

        if ($savedQuery?->isSql()) {
            return redirect()->route('sql.index', ['file' => $savedQuery->id]);
        }

        return view('queries.builder', [
            'datasets' => Dataset::where('status', 'done')->whereNotNull('table_name')->orderBy('name')->get(['id', 'name']),
            'savedQuery' => $savedQuery ? (new SavedQueryResource($savedQuery))->resolve($request) : null,
            'folders' => QueryFolder::options(),
            'folderId' => $savedQuery?->folder_id ?? $request->integer('folder') ?: null,
        ]);
    }
}
