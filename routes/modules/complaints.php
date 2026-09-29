<?php

use App\Http\Controllers\ComplaintController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-complaints'])
    ->controller(ComplaintController::class)
    ->prefix('hr/complaints')
    ->name('hr.complaints.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-complaints')->name('store');
        Route::put('{complaint}', 'update')->middleware('permission:edit-complaints')->name('update');
        Route::put('{complaint}/resolve', 'resolve')->middleware('permission:resolve-complaints')->name('resolve');
        Route::put('{complaint}/status', 'changeStatus')->middleware('permission:resolve-complaints')->name('change-status');
        Route::put('{complaint}/assign', 'assign')->middleware('permission:assign-complaints')->name('assign');
        Route::put('{complaint}/follow-up', 'followUp')->middleware('permission:resolve-complaints')->name('follow-up');
        Route::get('{complaint}/document', 'document')->name('document');
        Route::delete('{complaint}', 'destroy')->middleware('permission:delete-complaints')->name('destroy');
    });
