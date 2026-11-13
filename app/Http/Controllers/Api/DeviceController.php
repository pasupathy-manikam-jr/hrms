<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Register this device's Firebase token for push notifications. Signing out removes it.
 */
class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'in:android,ios'],
        ]);
        /** @var User $user */
        $user = $request->user();

        // A token moves with the device: re-registering after another user signed in on it reassigns it.
        DeviceToken::query()->updateOrCreate(['token' => $data['token']], [
            'user_id' => $user->id,
            'personal_access_token_id' => $user->currentAccessToken()->id,
            'platform' => $data['platform'] ?? 'android',
        ]);

        return response()->json(['message' => __('Device registered.')]);
    }
}
