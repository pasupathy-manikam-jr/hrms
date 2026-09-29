<?php

use App\Http\Controllers\CalendarController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:view-calendar'])
    ->get('calendar', [CalendarController::class, 'index'])
    ->name('calendar.index');
