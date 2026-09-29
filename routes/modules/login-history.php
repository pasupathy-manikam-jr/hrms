<?php

use App\Http\Controllers\System\LoginHistoryController;
use Illuminate\Support\Facades\Route;

// Open to every signed-in user: without manage-login-history they only see their own logins.
Route::middleware(['auth', 'verified'])
    ->get('login-history', [LoginHistoryController::class, 'index'])
    ->name('login-history.index');
