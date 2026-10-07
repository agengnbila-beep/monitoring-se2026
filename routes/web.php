<?php

use App\Http\Controllers\Api\DatasetController as ApiDatasetController;
use App\Http\Controllers\Auth\LoginController as AuthLoginController;
use App\Http\Controllers\DatasetController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthLoginController::class, 'create'])->name('login');
    Route::post('/login', [AuthLoginController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthLoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::middleware('can:admin')->group(function () {
        Route::get('/data', [DatasetController::class, 'index'])->name('data');
        Route::post('/data/upload', [DatasetController::class, 'store'])->name('data.upload');
        Route::get('/data/{dataset}/preview', [DatasetController::class, 'preview'])->name('data.preview');
        Route::post('/data/{dataset}/confirm', [DatasetController::class, 'confirm'])->name('data.confirm');
        Route::delete('/data/{dataset}', [DatasetController::class, 'destroy'])->name('data.destroy');

        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/datasets', [ApiDatasetController::class, 'index'])->name('datasets.index');
            Route::get('/datasets/{dataset}/profile', [ApiDatasetController::class, 'profile'])->name('datasets.profile');
            Route::get('/datasets/{dataset}/preview', [ApiDatasetController::class, 'preview'])->name('datasets.preview');
        });
    });
});
