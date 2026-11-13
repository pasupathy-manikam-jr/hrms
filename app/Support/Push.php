<?php

namespace App\Support;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use function Illuminate\Support\defer;

/**
 * Push notifications to the mobile app through Firebase Cloud Messaging (HTTP v1).
 * Does nothing until services.fcm.credentials points at a Firebase service account JSON file.
 * Sending is deferred until after the response, so no queue worker is needed.
 */
class Push
{
    /**
     * @param  iterable<int|null>  $userIds
     * @param  array<string, string|int>  $data  e.g. ['type' => 'leave', 'id' => 5], for the app to open the right screen
     */
    public static function send(iterable $userIds, string $title, string $body, array $data = []): void
    {
        $credentials = self::credentials();
        $userIds = collect($userIds)->filter()->unique()->values();

        if ($credentials === null || $userIds->isEmpty()) {
            return;
        }

        defer(function () use ($credentials, $userIds, $title, $body, $data) {
            $url = "https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send";
            $accessToken = self::accessToken($credentials);

            // ponytail: one request per device; FCM has no multicast in v1. Fine for company-sized audiences.
            DeviceToken::query()->whereIn('user_id', $userIds)->each(function (DeviceToken $device) use ($url, $accessToken, $title, $body, $data) {
                $response = Http::withToken($accessToken)->post($url, ['message' => [
                    'token' => $device->token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map('strval', $data),
                ]]);

                if ($response->status() === 404 || $response->json('error.details.0.errorCode') === 'UNREGISTERED') {
                    $device->delete();
                } elseif ($response->failed()) {
                    Log::warning('FCM push failed', ['status' => $response->status(), 'body' => $response->body()]);
                }
            });
        });
    }

    /**
     * @return array{project_id: string, client_email: string, private_key: string}|null
     */
    private static function credentials(): ?array
    {
        $path = config('services.fcm.credentials');

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return null;
        }

        /** @var array{project_id: string, client_email: string, private_key: string} */
        return json_decode((string) file_get_contents($path), true);
    }

    /**
     * An OAuth access token for the service account (a signed JWT exchanged with Google), cached for 50 minutes.
     *
     * @param  array{project_id: string, client_email: string, private_key: string}  $credentials
     */
    private static function accessToken(array $credentials): string
    {
        return Cache::remember('fcm-access-token:'.$credentials['client_email'], 3000, function () use ($credentials) {
            $encode = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
            $now = time();
            $unsigned = $encode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode((string) json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);

            return (string) Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.$encode($signature),
            ])->throw()->json('access_token');
        });
    }
}
