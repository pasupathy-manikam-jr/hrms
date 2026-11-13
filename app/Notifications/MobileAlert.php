<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Something the employee should know about. Kept in their notification list (GET /api/v1/notifications)
 * and sent live through reverb-hub as event `notification` on `private-App.Models.User.{id}`.
 * Title and body are English keys plus placeholders, translated into the reader's language when shown.
 */
class MobileAlert extends Notification
{
    /**
     * @param  array<string, string|int>  $replace
     * @param  array{type: string, id: int}  $target  what it is about, so the app can open that screen
     */
    public function __construct(
        public string $title,
        public string $body,
        public array $replace,
        public array $target,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array{title: string, body: string, replace: array<string, string|int>, target: array{type: string, id: int}}
     */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'replace' => $this->replace, 'target' => $this->target];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $locale = $notifiable->lang ?? null;

        return (new BroadcastMessage([
            'id' => $this->id,
            'title' => __($this->title, $this->replace, $locale),
            'body' => __($this->body, $this->replace, $locale),
            'target' => $this->target,
            'unread' => true,
            'created_at' => now()->toIso8601String(),
        ]))->onConnection('sync');
    }

    /** Event name the app binds to. */
    public function broadcastAs(): string
    {
        return 'notification';
    }
}
