<?php

use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-employee-transfers'])
    ->controller(TransferController::class)
    ->prefix('hr/transfers')
    ->name('hr.transfers.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-employee-transfers')->name('store');
        Route::put('{transfer}', 'update')->middleware('permission:edit-employee-transfers')->name('update');
        Route::put('{transfer}/approve', 'approve')->middleware('permission:approve-employee-transfers')->name('approve');
        Route::put('{transfer}/reject', 'reject')->middleware('permission:reject-employee-transfers')->name('reject');
        Route::get('{transfer}/document', 'document')->name('document');
        Route::delete('{transfer}', 'destroy')->middleware('permission:delete-employee-transfers')->name('destroy');
    });
