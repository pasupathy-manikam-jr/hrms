<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Profile photos live on the private disk; any signed-in user may see colleagues' photos.
Route::middleware('auth')->get('users/{user}/avatar', function (User $user) {
    abort_unless($user->avatar_path && Storage::disk('local')->exists($user->avatar_path), 404);

    return Storage::disk('local')->response($user->avatar_path, headers: ['Cache-Control' => 'private, max-age=31536000, immutable']);
})->name('users.avatar');
