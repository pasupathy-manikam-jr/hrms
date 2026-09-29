<?php

use App\Http\Controllers\DepartmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-departments'])
    ->controller(DepartmentController::class)
    ->prefix('hr/departments')
    ->name('hr.departments.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-departments')->name('store');
        Route::put('{department}', 'update')->middleware('permission:edit-departments')->name('update');
        Route::put('{department}/toggle-status', 'toggleStatus')->middleware('permission:toggle-status-departments')->name('toggle-status');
        Route::delete('{department}', 'destroy')->middleware('permission:delete-departments')->name('destroy');
    });
