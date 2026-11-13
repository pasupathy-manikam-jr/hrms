<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Mobile notifications (MobileAlert): each user listens on their own private channel, signed via the API token.
Broadcast::channel('App.Models.User.{id}', fn (User $user, int $id) => $user->id === $id, ['guards' => ['sanctum']]);
