<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\CustomQuestion;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomQuestionController extends Controller
{
    public function index(Request $request): Response
    {
        $query = CustomQuestion::query()
            ->visibleTo($request->user())
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('type'), fn ($q, $type) => $q->where('type', $type));

        return Inertia::render('hr/recruitment/custom-questions/index', [
            'customQuestions' => TableQuery::paginate($query, $request, ['question'], ['question', 'sort_order', 'created_at'], 'sort_order'),
            'filters' => TableQuery::filters($request, ['status', 'type']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CustomQuestion::create($this->validated($request));

        return $this->done(__('Custom question created successfully.'));
    }

    public function update(Request $request, CustomQuestion $customQuestion): RedirectResponse
    {
        abort_unless($customQuestion->isVisibleTo($request->user()), 403);
        $customQuestion->update($this->validated($request));

        return $this->done(__('Custom question updated successfully.'));
    }

    public function destroy(Request $request, CustomQuestion $customQuestion): RedirectResponse
    {
        abort_unless($customQuestion->isVisibleTo($request->user()), 403);
        $customQuestion->delete();

        return $this->done(__('Custom question deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(CustomQuestion::TYPES)],
            'options' => [Rule::requiredIf(in_array($request->input('type'), CustomQuestion::OPTION_TYPES, true)), 'nullable', 'array', 'max:50'],
            'options.*' => ['required', 'string', 'max:255', 'distinct'],
            'required' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['required', Rule::in(CustomQuestion::STATUSES)],
        ]);

        $data['options'] = in_array($data['type'], CustomQuestion::OPTION_TYPES, true) ? array_values($data['options']) : null;
        $data['sort_order'] ??= 0;

        return $data;
    }
}
