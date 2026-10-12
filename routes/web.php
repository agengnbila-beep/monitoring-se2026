<?php

use App\Http\Controllers\AnalyticsPageController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\DashboardController as ApiDashboardController;
use App\Http\Controllers\Api\DatasetController as ApiDatasetController;
use App\Http\Controllers\Api\SavedQueryController;
use App\Http\Controllers\Api\SqlController;
use App\Http\Controllers\Api\WidgetController;
use App\Http\Controllers\Auth\LoginController as AuthLoginController;
use App\Http\Controllers\DashboardPageController;
use App\Http\Controllers\DatasetColumnController;
use App\Http\Controllers\DatasetController;
use App\Http\Controllers\QueryPageController;
use App\Http\Controllers\SqlPageController;
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
        Route::middleware('throttle:widgets')->group(function () {
            Route::get('/widgets/{widget}/data', [WidgetController::class, 'data'])->name('widgets.data');
            Route::get('/dashboards/{dashboard}/filters/{key}/options', [WidgetController::class, 'filterOptions'])->name('dashboards.filter-options');
        });
    });

    Route::middleware('can:admin')->group(function () {
        Route::get('/data', [DatasetController::class, 'index'])->name('data');
        Route::post('/data/upload', [DatasetController::class, 'store'])->name('data.upload');
        Route::get('/data/{dataset}/preview', [DatasetController::class, 'preview'])->name('data.preview');
        Route::post('/data/{dataset}/confirm', [DatasetController::class, 'confirm'])->name('data.confirm');
        Route::get('/data/{dataset}', [DatasetController::class, 'show'])->name('data.show');
        Route::patch('/data/{dataset}/columns', [DatasetColumnController::class, 'update'])->name('data.columns.update');
        Route::delete('/data/{dataset}', [DatasetController::class, 'destroy'])->name('data.destroy');

        Route::get('/analitik', [AnalyticsPageController::class, 'index'])->name('analitik.index');
        Route::redirect('/queries', '/analitik')->name('queries.index');
        Route::get('/queries/builder', [QueryPageController::class, 'builder'])->name('queries.builder');
        Route::get('/sql', [SqlPageController::class, 'index'])->name('sql.index');

        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/datasets', [ApiDatasetController::class, 'index'])->name('datasets.index');
            Route::get('/datasets/{dataset}/profile', [ApiDatasetController::class, 'profile'])->name('datasets.profile');
            Route::get('/datasets/{dataset}/preview', [ApiDatasetController::class, 'preview'])->name('datasets.preview');

            Route::middleware('throttle:queries')->group(function () {
                Route::post('/queries/run', [SavedQueryController::class, 'run'])->name('queries.run');
                Route::get('/queries/{savedQuery}/run', [SavedQueryController::class, 'runSaved'])->name('queries.run-saved');
            });
            Route::apiResource('queries', SavedQueryController::class)->parameters(['queries' => 'savedQuery']);

            Route::prefix('sql')->name('sql.')->group(function () {
                Route::post('/run', [SqlController::class, 'run'])->middleware('throttle:queries')->name('run');
                Route::post('/files', [SqlController::class, 'storeFile'])->name('files.store');
                Route::get('/files/{savedQuery}', [SqlController::class, 'showFile'])->name('files.show');
                Route::put('/files/{savedQuery}', [SqlController::class, 'updateFile'])->name('files.update');
            });

            Route::prefix('analitik')->name('analitik.')->group(function () {
                Route::post('/folders', [AnalyticsController::class, 'storeFolder'])->name('folders.store');
                Route::put('/folders/{folder}', [AnalyticsController::class, 'updateFolder'])->name('folders.update');
                Route::delete('/folders/{folder}', [AnalyticsController::class, 'destroyFolder'])->name('folders.destroy');
                Route::put('/files/{savedQuery}/folder', [AnalyticsController::class, 'moveFile'])->name('files.move');
                Route::delete('/files/{savedQuery}', [AnalyticsController::class, 'destroyFile'])->name('files.destroy');
            });

            Route::apiResource('dashboards', ApiDashboardController::class)->only(['store', 'update', 'destroy']);
            Route::post('/dashboards/{dashboard}/widgets', [WidgetController::class, 'store'])->name('widgets.store');
            Route::put('/dashboards/{dashboard}/widgets/order', [WidgetController::class, 'reorder'])->name('widgets.reorder');
            Route::put('/widgets/{widget}', [WidgetController::class, 'update'])->name('widgets.update');
            Route::delete('/widgets/{widget}', [WidgetController::class, 'destroy'])->name('widgets.destroy');
        });
    });
});
