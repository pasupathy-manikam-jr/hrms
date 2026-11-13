<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\PayslipController;
use App\Http\Controllers\Api\SurveyController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RestrictIp;
use Illuminate\Support\Facades\Route;

/*
 * Mobile app API (Sanctum bearer tokens). Same permission names and scoping as the web routes.
 */
Route::prefix('v1')->name('api.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login');

    Route::middleware(['auth:sanctum', EnsureUserIsActive::class, 'verified', RestrictIp::class])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');

        Route::controller(AttendanceController::class)->prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/', 'index')->middleware('permission:manage-attendance-records')->name('index');
            Route::middleware('permission:clock-in-out')->group(function () {
                Route::get('today', 'today')->name('today');
                Route::post('clock-in', 'clockIn')->name('clock-in');
                Route::post('clock-out', 'clockOut')->name('clock-out');
            });
        });

        Route::controller(LeaveController::class)->prefix('leave')->name('leave.')->middleware('permission:manage-leave-applications')->group(function () {
            Route::get('types', 'types')->name('types');
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('permission:create-leave-applications')->name('store');
            Route::delete('{leaveApplication}', 'destroy')->whereNumber('leaveApplication')->middleware('permission:delete-leave-applications')->name('destroy');
        });

        Route::controller(PayslipController::class)->prefix('payslips')->name('payslips.')->middleware('permission:manage-payslips')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{payslip}', 'show')->whereNumber('payslip')->name('show');
            Route::get('{payslip}/pdf', 'pdf')->whereNumber('payslip')->middleware('permission:download-payslips')->name('pdf');
        });

        Route::controller(AnnouncementController::class)->prefix('announcements')->name('announcements.')->middleware('permission:manage-announcements')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{announcement}', 'show')->whereNumber('announcement')->name('show');
        });

        Route::controller(SurveyController::class)->prefix('surveys')->name('surveys.')->middleware('permission:take-engagement-survey')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('{campaign}', 'show')->whereNumber('campaign')->name('show');
            Route::post('{campaign}', 'store')->whereNumber('campaign')->middleware('throttle:20,1')->name('store');
        });

        Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');

        Route::get('meetings', [MeetingController::class, 'index'])->middleware('permission:manage-meetings')->name('meetings.index');
    });
});
