<?php

use App\Http\Controllers\Documents\DocumentCategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-document-categories'])
    ->controller(DocumentCategoryController::class)
    ->prefix('hr/documents/document-categories')
    ->name('hr.documents.document-categories.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-document-categories')->name('store');
        Route::put('{documentCategory}', 'update')->middleware('permission:edit-document-categories')->name('update');
        // The demo has no toggle-status-document-categories permission, so the lock needs edit-document-categories.
        Route::put('{documentCategory}/toggle-status', 'toggleStatus')->middleware('permission:edit-document-categories')->name('toggle-status');
        Route::delete('{documentCategory}', 'destroy')->middleware('permission:delete-document-categories')->name('destroy');
    });
