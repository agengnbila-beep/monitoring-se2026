<?php

use App\Http\Controllers\DatasetController;
use App\Models\Dataset;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

Route::get('/data', function () {

    $datasets = Dataset::latest()->get();

    return view('data.index', [
        'datasets' => $datasets
    ]);

})->name('data');


Route::post('/data/upload', [DatasetController::class, 'upload'])
    ->name('data.upload');