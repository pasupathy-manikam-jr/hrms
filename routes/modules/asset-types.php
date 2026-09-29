<?php

use App\Http\Controllers\AssetTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-asset-types'])
    ->controller(AssetTypeController::class)
    ->prefix('hr/asset-types')
    ->name('hr.asset-types.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-asset-types')->name('store');
        Route::put('{assetType}', 'update')->middleware('permission:edit-asset-types')->name('update');
        Route::delete('{assetType}', 'destroy')->middleware('permission:delete-asset-types')->name('destroy');
    });
