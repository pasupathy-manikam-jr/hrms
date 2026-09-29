<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\CandidateSource;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CandidateSourceController extends Controller
{
    public function index(Request $request): Response
    {
        $query = CandidateSource::query()
            ->visibleTo($request->user())
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));

        return Inertia::render('hr/recruitment/candidate-sources/index', [
            'candidateSources' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'created_at']),
            'filters' => TableQuery::filters($request, ['status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CandidateSource::create($this->validated($request));

        return $this->done(__('Candidate source created successfully.'));
    }

    public function update(Request $request, CandidateSource $candidateSource): RedirectResponse
    {
        abort_unless($candidateSource->isVisibleTo($request->user()), 403);
        $candidateSource->update($this->validated($request));

        return $this->done(__('Candidate source updated successfully.'));
    }

    public function toggleStatus(Request $request, CandidateSource $candidateSource): RedirectResponse
    {
        abort_unless($candidateSource->isVisibleTo($request->user()), 403);
        $candidateSource->update(['status' => $candidateSource->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Candidate source status updated.'));
    }

    public function destroy(Request $request, CandidateSource $candidateSource): RedirectResponse
    {
        abort_unless($candidateSource->isVisibleTo($request->user()), 403);
        $candidateSource->delete();

        return $this->done(__('Candidate source deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(CandidateSource::STATUSES)],
        ]);
    }
}
