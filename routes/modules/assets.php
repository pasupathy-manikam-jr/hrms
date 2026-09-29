<?php

use App\Http\Controllers\AssetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-assets'])
    ->controller(AssetController::class)
    ->name('hr.assets.')
    ->group(function () {
        Route::get('hr/assets-dashboard', 'dashboard')->name('dashboard');
        Route::get('hr/assets-depreciation-report', 'depreciationReport')->name('depreciation-report');

        Route::prefix('hr/assets')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('export-depreciation-csv', 'exportDepreciation')->middleware('permission:export-assets')->name('export-depreciation-csv');
            Route::get('export', 'export')->middleware('permission:export-assets')->name('export');
            Route::get('download-template', 'template')->middleware('permission:import-assets')->name('download.template');
            Route::post('import', 'import')->middleware(['permission:import-assets', 'throttle:10,1'])->name('import');
            Route::get('{asset}', 'show')->whereNumber('asset')->name('show');
            Route::post('/', 'store')->middleware('permission:create-assets')->name('store');
            Route::put('{asset}', 'update')->middleware('permission:edit-assets')->name('update');
            Route::delete('{asset}', 'destroy')->middleware('permission:delete-assets')->name('destroy');
            Route::post('{asset}/assign', 'assign')->middleware('permission:assign-assets')->name('assign');
            Route::post('{asset}/return', 'return')->middleware('permission:assign-assets')->name('return');
        });
    });
