<?php

use App\Http\Controllers\Documents\ContractTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-contract-types'])
    ->controller(ContractTypeController::class)
    ->prefix('hr/contracts/contract-types')
    ->name('hr.contracts.contract-types.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-contract-types')->name('store');
        Route::put('{contractType}', 'update')->middleware('permission:edit-contract-types')->name('update');
        // The demo has no toggle-status-contract-types permission, so the lock needs edit-contract-types.
        Route::put('{contractType}/toggle-status', 'toggleStatus')->middleware('permission:edit-contract-types')->name('toggle-status');
        Route::delete('{contractType}', 'destroy')->middleware('permission:delete-contract-types')->name('destroy');
    });
