<?php

use App\Http\Controllers\Documents\ContractTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-contract-templates'])
    ->controller(ContractTemplateController::class)
    ->prefix('hr/contracts/contract-templates')
    ->name('hr.contracts.contract-templates.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{contractTemplate}', 'show')->whereNumber('contractTemplate')->name('show');
        Route::post('/', 'store')->middleware('permission:create-contract-templates')->name('store');
        Route::put('{contractTemplate}', 'update')->middleware('permission:edit-contract-templates')->name('update');
        Route::delete('{contractTemplate}', 'destroy')->middleware('permission:delete-contract-templates')->name('destroy');
        // The demo has no toggle-status-contract-templates permission, so the lock needs edit-contract-templates.
        Route::put('{contractTemplate}/toggle-status', 'toggleStatus')->middleware('permission:edit-contract-templates')->name('toggle-status');
        Route::get('{contractTemplate}/download', 'download')->name('download');
        Route::get('{contractTemplate}/preview', 'preview')->name('preview');
    });
