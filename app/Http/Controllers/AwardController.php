<?php

namespace App\Http\Controllers;

use App\Models\Award;
use App\Models\AwardType;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AwardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        $query = Award::query()
            ->visibleTo($user)
            ->with('employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path', 'awardType:id,name')
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->integer('award_type_id'), fn ($q, $id) => $q->where('award_type_id', $id))
            ->when($request->date('date_from'), fn ($q, $date) => $q->whereDate('award_date', '>=', $date))
            ->when($request->date('date_to'), fn ($q, $date) => $q->whereDate('award_date', '<=', $date))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('gift', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('employee.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))));

        return Inertia::render('hr/awards/index', [
            'awards' => TableQuery::paginate($query, $request, [], ['award_date', 'monetary_value', 'created_at']),
            'awardTypes' => AwardType::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'employees' => Award::employeeOptions($user),
            'filters' => TableQuery::filters($request, ['employee_id', 'award_type_id', 'date_from', 'date_to']),
        ]);
    }

    public function show(Request $request, Award $award): Response
    {
        abort_unless($award->isVisibleTo($this->user($request)), 404);

        return Inertia::render('hr/awards/show', [
            'award' => $award->load([
                'employee:id,user_id,employee_id,gender,department_id,designation_id',
                'employee.user:id,name,email,avatar_path',
                'employee.department:id,name',
                'employee.designation:id,name',
                'awardType:id,name,description',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Award::create([...$this->validated($request), 'created_by' => $request->user()?->id]);

        return $this->done(__('Award created successfully.'));
    }

    public function update(Request $request, Award $award): RedirectResponse
    {
        abort_unless($award->isVisibleTo($this->user($request)), 404);

        $award->update($this->validated($request));

        return $this->done(__('Award updated successfully.'));
    }

    public function destroy(Request $request, Award $award): RedirectResponse
    {
        abort_unless($award->isVisibleTo($this->user($request)), 404);

        $award->delete();

        return $this->done(__('Award deleted successfully.'));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'award_type_id' => ['required', 'integer', Rule::exists('award_types', 'id')],
            'award_date' => ['required', 'date_format:Y-m-d'],
            'gift' => ['nullable', 'string', 'max:255'],
            'monetary_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
