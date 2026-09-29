<?php

use App\Http\Controllers\Documents\DocumentTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-document-templates'])
    ->controller(DocumentTemplateController::class)
    ->prefix('hr/documents/document-templates')
    ->name('hr.documents.document-templates.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{documentTemplate}', 'show')->whereNumber('documentTemplate')->name('show');
        Route::post('/', 'store')->middleware('permission:create-document-templates')->name('store');
        Route::put('{documentTemplate}', 'update')->middleware('permission:edit-document-templates')->name('update');
        Route::delete('{documentTemplate}', 'destroy')->middleware('permission:delete-document-templates')->name('destroy');
        // The demo has no toggle-status-document-templates permission, so the lock needs edit-document-templates.
        Route::put('{documentTemplate}/toggle-status', 'toggleStatus')->middleware('permission:edit-document-templates')->name('toggle-status');
        Route::get('{documentTemplate}/download', 'download')->name('download');
        Route::get('{documentTemplate}/preview', 'preview')->name('preview');
    });
