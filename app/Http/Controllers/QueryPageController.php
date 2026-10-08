<?php

namespace App\Http\Controllers;

use App\Http\Resources\SavedQueryResource;
use App\Models\Dataset;
use App\Models\SavedQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QueryPageController extends Controller
{
    public function index(): View
    {
        return view('queries.index', [
            'queries' => SavedQuery::with('dataset:id,name')
                ->where('type', SavedQuery::TYPE_BUILDER)
                ->latest('updated_at')
                ->get(),
        ]);
    }

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
        ]);
    }

    public function destroy(SavedQuery $savedQuery): RedirectResponse
    {
        if ($savedQuery->widgets()->exists()) {
            return back()->with('error', 'Query masih dipakai widget dashboard.');
        }

        $savedQuery->delete();

        return redirect()->route('queries.index')->with('success', 'Query dihapus.');
    }
}
