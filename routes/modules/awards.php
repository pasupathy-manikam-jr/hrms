<?php

use App\Http\Controllers\AwardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-awards'])
    ->controller(AwardController::class)
    ->prefix('hr/awards')
    ->name('hr.awards.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{award}', 'show')->whereNumber('award')->name('show');
        Route::post('/', 'store')->middleware('permission:create-awards')->name('store');
        Route::put('{award}', 'update')->middleware('permission:edit-awards')->name('update');
        Route::delete('{award}', 'destroy')->middleware('permission:delete-awards')->name('destroy');
    });
