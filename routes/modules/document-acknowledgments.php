<?php

use App\Http\Controllers\Documents\DocumentAcknowledgmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-document-acknowledgments'])
    ->controller(DocumentAcknowledgmentController::class)
    ->prefix('hr/documents/document-acknowledgments')
    ->name('hr.documents.document-acknowledgments.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-document-acknowledgments')->name('store');
        Route::put('{documentAcknowledgment}', 'update')->middleware('permission:edit-document-acknowledgments')->name('update');
        // Only the assignee may acknowledge (checked in the controller); employees hold just manage-own.
        Route::put('{documentAcknowledgment}/acknowledge', 'acknowledge')->name('acknowledge');
        Route::delete('{documentAcknowledgment}', 'destroy')->middleware('permission:delete-document-acknowledgments')->name('destroy');
    });
