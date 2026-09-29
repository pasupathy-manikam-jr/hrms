<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canSeeAll = $user->can('manage-login-history');
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        $query = LoginHistory::query()
            ->visibleTo($user)
            ->with('user:id,name,email,avatar_path', 'user.roles:id,name,label')
            ->when($request->input('date'), fn ($q, $date) => $q->whereDate('logged_in_at', $date))
            ->when($canSeeAll ? $request->input('user_id') : null, fn ($q, $userId) => $q->where('user_id', $userId));

        return Inertia::render('login-history/index', [
            'loginHistory' => TableQuery::paginate($query, $request, ['ip', 'browser', 'os', 'device'], ['logged_in_at', 'ip', 'browser'], 'logged_in_at'),
            'filters' => TableQuery::filters($request, ['date', 'user_id']),
            'users' => $canSeeAll ? User::query()->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }
}
