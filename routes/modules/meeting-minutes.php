<?php

use App\Http\Controllers\MeetingMinuteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-meeting-minutes'])
    ->controller(MeetingMinuteController::class)
    ->prefix('meetings/meeting-minutes')
    ->name('meetings.meeting-minutes.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-meeting-minutes')->name('store');
        Route::put('{meetingMinute}', 'update')->middleware('permission:edit-meeting-minutes')->name('update');
        Route::delete('{meetingMinute}', 'destroy')->middleware('permission:delete-meeting-minutes')->name('destroy');
    });
