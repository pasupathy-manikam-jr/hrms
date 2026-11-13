<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\MobileAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Notify users in the mobile app: saved to their notification list and broadcast live through reverb-hub
 * (when BROADCAST_CONNECTION=reverb). Sent after the response, so no queue worker is needed and a
 * reverb-hub outage never breaks the action that triggered it.
 */
class Push
{
    /**
     * @param  iterable<int|null>  $userIds
     * @param  string  $title  English key, translated per reader
     * @param  string  $body  English key with :placeholders
     * @param  array<string, string|int>  $replace
     * @param  array{type: string, id: int}  $target
     */
    public static function send(iterable $userIds, string $title, string $body, array $replace, array $target): void
    {
        $userIds = collect($userIds)->filter()->unique()->values();

        if ($userIds->isEmpty()) {
            return;
        }

        defer(function () use ($userIds, $title, $body, $replace, $target) {
            // One at a time, so one failure doesn't stop the rest.
            User::query()->whereIn('id', $userIds)->each(function (User $user) use ($title, $body, $replace, $target) {
                try {
                    Notification::send($user, new MobileAlert($title, $body, $replace, $target));
                } catch (Throwable $e) {
                    Log::warning('Mobile notification failed', ['user_id' => $user->id, 'target' => $target, 'error' => $e->getMessage()]);
                }
            });
        });
    }
}
