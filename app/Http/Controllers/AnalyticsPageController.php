<?php

namespace App\Http\Controllers;

use App\Models\QueryFolder;
use App\Models\SavedQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsPageController extends Controller
{
    /**
     * Isi satu folder Analitik (atau akar): subfolder dan file analitik.
     */
    public function index(Request $request): View
    {
        $folder = $request->filled('folder') ? QueryFolder::findOrFail($request->integer('folder')) : null;

        return view('analitik.index', [
            'folder' => $folder,
            'breadcrumb' => $folder?->ancestry() ?? [],
            'subfolders' => QueryFolder::where('parent_id', $folder?->id)->withCount(['children', 'queries'])->orderBy('name')->get(),
            'files' => SavedQuery::with('dataset:id,name')->where('folder_id', $folder?->id)->orderBy('name')->get(),
            'folderOptions' => QueryFolder::options(),
        ]);
    }
}
