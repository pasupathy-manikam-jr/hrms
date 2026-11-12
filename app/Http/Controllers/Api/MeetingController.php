<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Meetings the user organises or attends. ?upcoming=1 limits to today onwards.
 */
class MeetingController extends Controller
{
    /**
     * @return LengthAwarePaginator<int, Meeting>
     */
    public function index(Request $request): LengthAwarePaginator
    {
        /** @var User $user */
        $user = $request->user();

        $query = Meeting::query()->visibleTo($user)
            ->with(['type:id,name,color', 'room:id,name,type', 'organizer:id,name,email,avatar_path', 'attendees:id,name,avatar_path'])
            ->when($request->boolean('upcoming'), fn ($q) => $q->whereDate('meeting_date', '>=', today()));

        return TableQuery::paginate($query, $request, ['title'], ['meeting_date'], 'meeting_date');
    }
}
