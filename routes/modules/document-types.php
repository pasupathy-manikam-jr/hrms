<?php

use App\Http\Controllers\DocumentTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-document-types'])
    ->controller(DocumentTypeController::class)
    ->prefix('hr/document-types')
    ->name('hr.document-types.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-document-types')->name('store');
        Route::put('{documentType}', 'update')->middleware('permission:edit-document-types')->name('update');
        Route::delete('{documentType}', 'destroy')->middleware('permission:delete-document-types')->name('destroy');
    });
