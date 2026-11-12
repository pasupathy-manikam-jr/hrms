<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Announcements the user may see; opening one marks it viewed, as on the web.
 */
class AnnouncementController extends Controller
{
    /**
     * @return LengthAwarePaginator<int, Announcement>
     */
    public function index(Request $request): LengthAwarePaginator
    {
        /** @var User $user */
        $user = $request->user();

        $query = Announcement::query()->visibleTo($user)
            ->withStatus(in_array($request->input('status'), Announcement::STATUSES, true) ? $request->string('status')->toString() : 'active');

        return TableQuery::paginate($query, $request, ['title', 'description'], ['start_date', 'created_at'], 'start_date');
    }

    public function show(Request $request, int $announcement): Announcement
    {
        /** @var User $user */
        $user = $request->user();

        $record = Announcement::query()->visibleTo($user)->findOrFail($announcement);
        $record->viewers()->syncWithoutDetaching([$user->id]);

        return $record;
    }
}
