<?php

use App\Http\Controllers\MeetingTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-meeting-types'])
    ->controller(MeetingTypeController::class)
    ->prefix('meetings/meeting-types')
    ->name('meetings.meeting-types.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-meeting-types')->name('store');
        Route::put('{meetingType}', 'update')->middleware('permission:edit-meeting-types')->name('update');
        // The demo has no toggle-status-meeting-types permission, so the lock needs edit-meeting-types.
        Route::put('{meetingType}/toggle-status', 'toggleStatus')->middleware('permission:edit-meeting-types')->name('toggle-status');
        Route::delete('{meetingType}', 'destroy')->middleware('permission:delete-meeting-types')->name('destroy');
    });
