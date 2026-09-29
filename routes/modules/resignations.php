<?php

use App\Http\Controllers\ResignationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-resignations'])
    ->controller(ResignationController::class)
    ->prefix('hr/resignations')
    ->name('hr.resignations.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-resignations')->name('store');
        Route::put('{resignation}', 'update')->middleware('permission:edit-resignations')->name('update');
        Route::put('{resignation}/change-status', 'changeStatus')->middleware('permission:approve-resignations|reject-resignations')->name('change-status');
        Route::get('{resignation}/document', 'document')->name('document');
        Route::delete('{resignation}', 'destroy')->middleware('permission:delete-resignations')->name('destroy');
    });
