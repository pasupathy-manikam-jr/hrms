<?php

use App\Http\Controllers\EmployeeController;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-employees'])
    ->controller(EmployeeController::class)
    ->prefix('hr/employees')
    ->name('hr.employees.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('export', 'export')->middleware('permission:export-employee')->name('export');
        Route::get('download-template', 'template')->middleware('permission:import-employee')->name('download.template');
        Route::post('import', 'import')->middleware(['permission:import-employee', 'throttle:10,1'])->name('import');
        Route::get('create', 'create')->middleware('permission:create-employees')->name('create');
        Route::get('{employee}/edit', 'edit')->whereNumber('employee')->middleware('permission:edit-employees')->name('edit');
        Route::get('{employee}/documents/{document}', 'document')->whereNumber('employee')->name('document');
        Route::get('{employee}', 'show')->whereNumber('employee')->name('show');
        // Precognitive requests let the wizard validate each step on the server before moving on.
        Route::post('/', 'store')->middleware(['permission:create-employees', HandlePrecognitiveRequests::class])->name('store');
        Route::put('{employee}', 'update')->middleware(['permission:edit-employees', HandlePrecognitiveRequests::class])->name('update');
        Route::delete('{employee}', 'destroy')->middleware('permission:delete-employees')->name('destroy');
    });
