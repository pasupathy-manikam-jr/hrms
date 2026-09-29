<?php

use App\Http\Controllers\MeetingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-meetings'])
    ->controller(MeetingController::class)
    ->prefix('meetings/meetings')
    ->name('meetings.meetings.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{meeting}', 'show')->whereNumber('meeting')->name('show');
        Route::post('/', 'store')->middleware('permission:create-meetings')->name('store');
        Route::put('{meeting}', 'update')->middleware('permission:edit-meetings')->name('update');
        Route::put('{meeting}/status', 'changeStatus')->middleware('permission:edit-meetings')->name('change-status');
        Route::delete('{meeting}', 'destroy')->middleware('permission:delete-meetings')->name('destroy');
    });
