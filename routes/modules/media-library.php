<?php

use App\Http\Controllers\MediaLibraryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-media'])
    ->controller(MediaLibraryController::class)
    ->group(function () {
        Route::get('media-library', 'index')->name('media-library');

        Route::prefix('media-library')->name('media-library.')->group(function () {
            Route::post('media', 'store')->middleware('permission:create-media')->name('store');
            Route::put('media/{media}', 'update')->middleware('permission:edit-media')->name('update');
            Route::delete('media/{media}', 'destroy')->middleware('permission:delete-media')->name('destroy');
            Route::get('media/{media}/download', 'download')->middleware('permission:download-media')->name('download');
            Route::get('media/{media}/preview', 'preview')->middleware('permission:view-media')->name('preview');

            Route::middleware('permission:manage-media-directories')->prefix('directories')->name('directories.')->group(function () {
                Route::post('/', 'storeDirectory')->middleware('permission:create-media-directories')->name('store');
                Route::put('{mediaDirectory}', 'updateDirectory')->middleware('permission:edit-media-directories')->name('update');
                Route::delete('{mediaDirectory}', 'destroyDirectory')->middleware('permission:delete-media-directories')->name('destroy');
            });
        });
    });
