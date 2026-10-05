<?php

use App\Http\Controllers\Auth\LoginController as AuthLoginController;
use App\Http\Controllers\DatasetController;
use App\Models\Dataset;
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
        Route::get('/data', function () {
            return view('data.index', [
                'datasets' => Dataset::latest()->get(),
            ]);
        })->name('data');

        Route::post('/data/upload', [DatasetController::class, 'upload'])->name('data.upload');
    });
});
