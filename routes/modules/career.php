<?php

use App\Http\Controllers\Recruitment\CareerController;
use Illuminate\Support\Facades\Route;

// The public careers site: no login, like the demo's /{company}/career.
Route::controller(CareerController::class)
    ->prefix('career')
    ->name('career.')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('jobs/{jobPosting:job_code}', 'show')->name('show');
        Route::post('jobs/{jobPosting:job_code}/apply', 'apply')->middleware('throttle:5,1')->name('apply');
    });
