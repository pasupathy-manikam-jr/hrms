<?php

use App\Http\Controllers\MeetingRoomController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-meeting-rooms'])
    ->controller(MeetingRoomController::class)
    ->prefix('meetings/meeting-rooms')
    ->name('meetings.meeting-rooms.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:create-meeting-rooms')->name('store');
        Route::put('{meetingRoom}', 'update')->middleware('permission:edit-meeting-rooms')->name('update');
        Route::delete('{meetingRoom}', 'destroy')->middleware('permission:delete-meeting-rooms')->name('destroy');
    });
