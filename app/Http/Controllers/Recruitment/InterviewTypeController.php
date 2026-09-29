<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\InterviewType;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InterviewTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $query = InterviewType::query()
            ->visibleTo($request->user())
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));

        return Inertia::render('hr/recruitment/interview-types/index', [
            'interviewTypes' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'created_at']),
            'filters' => TableQuery::filters($request, ['status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        InterviewType::create($this->validated($request));

        return $this->done(__('Interview type created successfully.'));
    }

    public function update(Request $request, InterviewType $interviewType): RedirectResponse
    {
        abort_unless($interviewType->isVisibleTo($request->user()), 403);
        $interviewType->update($this->validated($request));

        return $this->done(__('Interview type updated successfully.'));
    }

    public function toggleStatus(Request $request, InterviewType $interviewType): RedirectResponse
    {
        abort_unless($interviewType->isVisibleTo($request->user()), 403);
        $interviewType->update(['status' => $interviewType->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Interview type status updated.'));
    }

    public function destroy(Request $request, InterviewType $interviewType): RedirectResponse
    {
        abort_unless($interviewType->isVisibleTo($request->user()), 403);
        $interviewType->delete();

        return $this->done(__('Interview type deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(InterviewType::STATUSES)],
        ]);
    }
}
