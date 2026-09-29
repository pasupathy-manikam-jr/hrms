<?php

use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-trips'])
    ->controller(TripController::class)
    ->prefix('hr/trips')
    ->name('hr.trips.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-trips')->name('store');
        Route::put('{trip}', 'update')->middleware('permission:edit-trips')->name('update');
        Route::put('{trip}/status', 'changeStatus')->middleware('permission:approve-trips')->name('change-status');
        Route::put('{trip}/advance', 'advance')->middleware('permission:manage-trip-expenses')->name('advance');
        Route::put('{trip}/expenses', 'expenses')->middleware('permission:manage-trip-expenses')->name('expenses');
        Route::put('{trip}/report', 'report')->name('report');
        Route::get('{trip}/document', 'document')->name('document');
        Route::delete('{trip}', 'destroy')->middleware('permission:delete-trips')->name('destroy');
    });
