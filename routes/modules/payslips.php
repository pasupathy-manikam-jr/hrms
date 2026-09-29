<?php

use App\Http\Controllers\Payroll\PayslipController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-payslips'])
    ->controller(PayslipController::class)
    ->prefix('hr/payslips')
    ->name('hr.payslips.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
    });
