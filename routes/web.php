<?php

use App\Http\Controllers\Api\DashboardController as ApiDashboardController;
use App\Http\Controllers\Api\DatasetController as ApiDatasetController;
use App\Http\Controllers\Api\SavedQueryController;
use App\Http\Controllers\Api\WidgetController;
use App\Http\Controllers\Auth\LoginController as AuthLoginController;
use App\Http\Controllers\DashboardPageController;
use App\Http\Controllers\DatasetColumnController;
use App\Http\Controllers\DatasetController;
use App\Http\Controllers\QueryPageController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthLoginController::class, 'create'])->name('login');
    Route::post('/login', [AuthLoginController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthLoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardPageController::class, 'index'])->name('dashboard');
    Route::get('/dashboards/{dashboard:slug}', [DashboardPageController::class, 'show'])->name('dashboards.show');

    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/dashboards', [ApiDashboardController::class, 'index'])->name('dashboards.index');
        Route::get('/dashboards/{dashboard}', [ApiDashboardController::class, 'show'])->name('dashboards.show');
        Route::get('/widgets/{widget}/data', [WidgetController::class, 'data'])->name('widgets.data');
    });

    Route::middleware('can:admin')->group(function () {
        Route::get('/data', [DatasetController::class, 'index'])->name('data');
        Route::post('/data/upload', [DatasetController::class, 'store'])->name('data.upload');
        Route::get('/data/{dataset}/preview', [DatasetController::class, 'preview'])->name('data.preview');
        Route::post('/data/{dataset}/confirm', [DatasetController::class, 'confirm'])->name('data.confirm');
        Route::get('/data/{dataset}', [DatasetController::class, 'show'])->name('data.show');
        Route::patch('/data/{dataset}/columns', [DatasetColumnController::class, 'update'])->name('data.columns.update');
        Route::delete('/data/{dataset}', [DatasetController::class, 'destroy'])->name('data.destroy');

        Route::get('/queries', [QueryPageController::class, 'index'])->name('queries.index');
        Route::get('/queries/builder', [QueryPageController::class, 'builder'])->name('queries.builder');
        Route::delete('/queries/{savedQuery}', [QueryPageController::class, 'destroy'])->name('queries.destroy');

        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/datasets', [ApiDatasetController::class, 'index'])->name('datasets.index');
            Route::get('/datasets/{dataset}/profile', [ApiDatasetController::class, 'profile'])->name('datasets.profile');
            Route::get('/datasets/{dataset}/preview', [ApiDatasetController::class, 'preview'])->name('datasets.preview');

            Route::post('/queries/run', [SavedQueryController::class, 'run'])->name('queries.run');
            Route::get('/queries/{savedQuery}/run', [SavedQueryController::class, 'runSaved'])->name('queries.run-saved');
            Route::apiResource('queries', SavedQueryController::class)->parameters(['queries' => 'savedQuery']);

            Route::apiResource('dashboards', ApiDashboardController::class)->only(['store', 'update', 'destroy']);
            Route::post('/dashboards/{dashboard}/widgets', [WidgetController::class, 'store'])->name('widgets.store');
            Route::put('/widgets/{widget}', [WidgetController::class, 'update'])->name('widgets.update');
            Route::delete('/widgets/{widget}', [WidgetController::class, 'destroy'])->name('widgets.destroy');
        });
    });
});
