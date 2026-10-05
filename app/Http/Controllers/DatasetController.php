<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DatasetController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx',
                'max:10240',
            ],
        ]);

        $file = $request->file('file');

        $originalName = $file->getClientOriginalName();

        $spreadsheet = IOFactory::load(
            $file->getRealPath()
        );

        $sheet = $spreadsheet->getActiveSheet();

        $rows = $sheet->getHighestRow();

        $highestColumn = $sheet->getHighestColumn();

        $columns = Coordinate::columnIndexFromString(
            $highestColumn
        );

        $file->store(
            'datasets',
            'local'
        );

        Dataset::create([
            'name' => pathinfo(
                $originalName,
                PATHINFO_FILENAME
            ),

            'original_filename' => $originalName,

            'rows' => $rows,

            'columns' => $columns,

            'status' => 'done',
        ]);

        return redirect()
            ->route('data')
            ->with(
                'success',
                'Dataset berhasil diupload.'
            );
    }
}
