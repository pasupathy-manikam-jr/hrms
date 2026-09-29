<?php

use App\Http\Controllers\TerminationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-terminations'])
    ->controller(TerminationController::class)
    ->prefix('hr/terminations')
    ->name('hr.terminations.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-terminations')->name('store');
        Route::put('{termination}', 'update')->middleware('permission:edit-terminations')->name('update');
        Route::put('{termination}/change-status', 'changeStatus')->middleware('permission:approve-terminations|reject-terminations')->name('change-status');
        Route::get('{termination}/document', 'document')->name('document');
        Route::delete('{termination}', 'destroy')->middleware('permission:delete-terminations')->name('destroy');
    });
