<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * The user's notification list: what the app shows when it wasn't connected to reverb-hub.
 */
class NotificationController extends Controller
{
    /**
     * Newest first, 30 a page. ?since=<ISO time> returns only newer ones.
     *
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $data = $request->validate(['since' => ['nullable', 'date']]);
        $user = $this->user($request);
        $page = $user->notifications()
            ->when($data['since'] ?? null, fn ($q, $since) => $q->where('created_at', '>', Carbon::parse($since)))
            ->paginate(30);

        return [
            'data' => $page->getCollection()->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'title' => __((string) ($n->data['title'] ?? ''), (array) ($n->data['replace'] ?? []), $user->lang),
                'body' => __((string) ($n->data['body'] ?? ''), (array) ($n->data['replace'] ?? []), $user->lang),
                'target' => $n->data['target'] ?? null,
                'unread' => $n->read_at === null,
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'unread_count' => $user->unreadNotifications()->count(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ];
    }

    /**
     * Mark the given notifications read, or all of them when no ids are sent.
     */
    public function markRead(Request $request): Response
    {
        $data = $request->validate(['ids' => ['nullable', 'array', 'max:100'], 'ids.*' => ['string']]);

        $this->user($request)->unreadNotifications()
            ->when($data['ids'] ?? null, fn ($q, $ids) => $q->whereIn('id', $ids))
            ->update(['read_at' => now()]);

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
