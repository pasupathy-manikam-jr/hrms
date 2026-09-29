<?php

use App\Http\Controllers\OrganizationChartController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:manage-organization-chart'])
    ->get('hr/organization-chart', [OrganizationChartController::class, 'index'])
    ->name('hr.organization-chart.index');
