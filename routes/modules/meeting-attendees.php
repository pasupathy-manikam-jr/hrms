<?php

use App\Http\Controllers\MeetingAttendeeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-meeting-attendees'])
    ->controller(MeetingAttendeeController::class)
    ->prefix('meetings/meeting-attendees')
    ->name('meetings.meeting-attendees.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-meeting-attendees')->name('store');
        Route::put('{meetingAttendee}', 'update')->middleware('permission:edit-meeting-attendees')->name('update');
        Route::delete('{meetingAttendee}', 'destroy')->middleware('permission:delete-meeting-attendees')->name('destroy');
    });
