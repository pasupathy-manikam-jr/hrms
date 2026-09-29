<?php

use App\Http\Controllers\LeaveBalanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-leave-balances'])
    ->controller(LeaveBalanceController::class)
    ->prefix('hr/leave-balances')
    ->name('hr.leave-balances.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('adjust', 'adjust')->middleware('permission:adjust-leave-balances')->name('adjust');
    });
