<?php

use App\Http\Controllers\Documents\HrDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-hr-documents'])
    ->controller(HrDocumentController::class)
    ->prefix('hr/documents/hr-documents')
    ->name('hr.documents.hr-documents.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-hr-documents')->name('store');
        Route::put('{hrDocument}', 'update')->middleware('permission:edit-hr-documents')->name('update');
        Route::put('{hrDocument}/status', 'changeStatus')->middleware('permission:edit-hr-documents')->name('change-status');
        Route::delete('{hrDocument}', 'destroy')->middleware('permission:delete-hr-documents')->name('destroy');
        Route::get('{hrDocument}/download', 'download')->name('download');
    });
