<?php

use App\Http\Controllers\DesignationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-designations'])
    ->controller(DesignationController::class)
    ->prefix('hr/designations')
    ->name('hr.designations.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-designations')->name('store');
        Route::put('{designation}', 'update')->middleware('permission:edit-designations')->name('update');
        Route::put('{designation}/toggle-status', 'toggleStatus')->middleware('permission:toggle-status-designations')->name('toggle-status');
        Route::delete('{designation}', 'destroy')->middleware('permission:delete-designations')->name('destroy');
    });
