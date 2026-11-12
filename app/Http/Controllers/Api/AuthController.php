<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Token sign-in for the mobile app. Same checks as the web login: password, active account,
 * verified email and, when the user has turned it on, a two-factor code.
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => __('Your account has been deactivated.')]);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $valid = filled($data['code'] ?? null) && app(TwoFactorAuthenticationProvider::class)->verify(
                Fortify::currentEncrypter()->decrypt($user->two_factor_secret), $data['code'],
            );

            if (! $valid) {
                throw ValidationException::withMessages(['code' => __('The provided two factor authentication code was invalid.')]);
            }
        }

        LoginHistory::record($user, $request->ip(), $request->userAgent());

        return response()->json([
            'token' => $user->createToken($data['device_name'])->plainTextToken,
            'user' => $this->profile($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()->delete();

        return response()->json(['message' => __('Signed out.')]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->profile($user));
    }

    /**
     * The user, their employee record and their permission names (so the app can hide what they can't do).
     *
     * @return array<string, mixed>
     */
    private function profile(User $user): array
    {
        $user->load('employee.branch:id,name', 'employee.department:id,name', 'employee.designation:id,name', 'employee.shift');

        return [
            ...$user->only(['id', 'name', 'email', 'lang', 'avatar']),
            'employee' => $user->employee,
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
