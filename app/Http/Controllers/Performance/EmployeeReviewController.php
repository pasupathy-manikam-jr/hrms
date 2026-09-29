<?php

namespace App\Http\Controllers\Performance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeReview;
use App\Models\PerformanceIndicator;
use App\Models\ReviewCycle;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeReviewController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $canManageAny = $user->can('manage-any-employee-reviews');
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        $query = EmployeeReview::query()
            ->visibleTo($user)
            ->with([
                'employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path', 'reviewer:id,name,email,avatar_path', 'reviewCycle:id,name,frequency',
                'ratings:id,employee_review_id,performance_indicator_id,rating,comments', 'ratings.indicator:id,name,category_id', 'ratings.indicator.category:id,name',
            ])
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->integer('reviewer_id'), fn ($q, $id) => $q->where('reviewer_id', $id))
            ->when($request->integer('review_cycle_id'), fn ($q, $id) => $q->where('review_cycle_id', $id))
            ->when($request->date('date_from'), fn ($q, $from) => $q->whereDate('review_date', '>=', $from))
            ->when($request->date('date_to'), fn ($q, $to) => $q->whereDate('review_date', '<=', $to))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('comments', 'like', "%{$search}%")
                ->orWhereHas('employee.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                ->orWhereHas('reviewCycle', fn ($c) => $c->where('name', 'like', "%{$search}%"))));

        $counts = TableQuery::countBy($query, 'status');
        $query->when(in_array($request->input('status'), EmployeeReview::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/performance/employee-reviews/index', [
            // Search is applied above so the status counts match it; TableQuery gets no searchable columns.
            'reviews' => TableQuery::paginate($query, $request, [], ['review_date', 'overall_rating', 'status', 'created_at']),
            ...$this->options($canManageAny),
            'statuses' => EmployeeReview::STATUSES,
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(EmployeeReview::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['status', 'employee_id', 'reviewer_id', 'review_cycle_id', 'date_from', 'date_to']),
        ]);
    }

    public function show(Request $request, EmployeeReview $employeeReview): Response
    {
        abort_unless(EmployeeReview::query()->visibleTo($request->user())->whereKey($employeeReview->id)->exists(), 404);

        return Inertia::render('hr/performance/employee-reviews/show', [
            'review' => $employeeReview->load([
                'employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path', 'employee.designation:id,name',
                'reviewer:id,name,email,avatar_path', 'reviewCycle:id,name,frequency',
                'ratings:id,employee_review_id,performance_indicator_id,rating,comments',
                'ratings.indicator:id,name,description,category_id,measurement_unit,target_value', 'ratings.indicator.category:id,name',
            ]),
        ]);
    }

    /**
     * The demo's "Schedule Review" page.
     */
    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('hr/performance/employee-reviews/create', [
            ...$this->options($user->can('manage-any-employee-reviews')),
            'statuses' => EmployeeReview::STATUSES,
        ]);
    }

    /**
     * Employee, reviewer and review cycle choices for the list filters and the schedule form.
     *
     * @return array<string, mixed>
     */
    private function options(bool $canManageAny): array
    {
        return [
            'employees' => $canManageAny
                ? Employee::query()->with('user:id,name')->orderBy('employee_id')->get(['id', 'user_id', 'employee_id'])
                    ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->user->name, 'employee_id' => $e->employee_id])
                : [],
            'reviewers' => $canManageAny ? User::query()->orderBy('name')->get(['id', 'name']) : [],
            'reviewCycles' => ReviewCycle::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $ratings] = $this->validated($request);

        DB::transaction(function () use ($request, $data, $ratings) {
            EmployeeReview::create([...$data, 'created_by' => $request->user()?->id])->syncRatings($ratings);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Review scheduled successfully.')]);

        return to_route('hr.performance.employee-reviews.index');
    }

    public function update(Request $request, EmployeeReview $employeeReview): RedirectResponse
    {
        $this->authorizeRecord($request, $employeeReview);
        [$data, $ratings] = $this->validated($request, $employeeReview);

        DB::transaction(function () use ($employeeReview, $data, $ratings) {
            $employeeReview->update($data);
            $employeeReview->syncRatings($ratings);
        });

        return $this->done(__('Employee review updated successfully.'));
    }

    /**
     * The demo's conduct page: rate every active indicator and submit, which completes the review.
     */
    public function conduct(Request $request, EmployeeReview $employeeReview): Response
    {
        $this->authorizeRecord($request, $employeeReview);
        abort_if($employeeReview->status === 'completed', 403, __('This review has already been completed.'));

        return Inertia::render('hr/performance/employee-reviews/conduct', [
            'review' => $employeeReview->load([
                'employee:id,user_id', 'employee.user:id,name', 'reviewCycle:id,name',
                'ratings:id,employee_review_id,performance_indicator_id,rating,comments',
            ]),
            'indicators' => PerformanceIndicator::query()->where('status', 'active')->with('category:id,name')
                ->orderBy('category_id')->orderBy('id')->get(['id', 'category_id', 'name', 'description', 'measurement_unit', 'target_value']),
        ]);
    }

    public function submitConduct(Request $request, EmployeeReview $employeeReview): RedirectResponse
    {
        $this->authorizeRecord($request, $employeeReview);
        abort_if($employeeReview->status === 'completed', 403, __('This review has already been completed.'));

        $data = $request->validate([
            'comments' => ['nullable', 'string', 'max:2000'],
            'ratings' => ['required', 'array', 'min:1'],
            'ratings.*.performance_indicator_id' => ['required', 'integer', 'distinct', Rule::exists('performance_indicators', 'id')->where('status', 'active')],
            'ratings.*.rating' => ['required', 'numeric', 'between:1,5', 'decimal:0,1'],
            'ratings.*.comments' => ['nullable', 'string', 'max:1000'],
        ]);

        $ratings = [];

        foreach ($data['ratings'] as $rating) {
            $ratings[(int) $rating['performance_indicator_id']] = ['rating' => $rating['rating'], 'comments' => $rating['comments'] ?? null];
        }

        DB::transaction(function () use ($employeeReview, $data, $ratings) {
            $employeeReview->update(['comments' => $data['comments'] ?? null, 'status' => 'completed', 'completion_date' => now()->toDateString()]);
            $employeeReview->syncRatings($ratings);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Review submitted successfully.')]);

        return to_route('hr.performance.employee-reviews.show', $employeeReview);
    }

    /**
     * The demo's "change status" action: move a review between scheduled, in progress and completed.
     */
    public function changeStatus(Request $request, EmployeeReview $employeeReview): RedirectResponse
    {
        $this->authorizeRecord($request, $employeeReview);
        $data = $request->validate(['status' => ['required', Rule::in(EmployeeReview::STATUSES)]]);

        $employeeReview->update($data);

        return $this->done(__('Review status updated.'));
    }

    public function destroy(Request $request, EmployeeReview $employeeReview): RedirectResponse
    {
        $this->authorizeRecord($request, $employeeReview);
        $employeeReview->delete();

        return $this->done(__('Employee review deleted successfully.'));
    }

    private function authorizeRecord(Request $request, EmployeeReview $employeeReview): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(EmployeeReview::query()->visibleTo($user)->whereKey($employeeReview->id)->exists(), 404);
    }

    /**
     * Validate the review and its ratings. overall_rating is never read from the request:
     * EmployeeReview::syncRatings() derives it from the stored indicator ratings.
     *
     * @return array{0: array<string, mixed>, 1: array<int, array{rating: float|int|string, comments: string|null}>}
     */
    private function validated(Request $request, ?EmployeeReview $employeeReview = null): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'reviewer_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'review_cycle_id' => ['required', 'integer', Rule::exists('review_cycles', 'id')],
            'review_date' => ['required', 'date_format:Y-m-d'],
            'comments' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(EmployeeReview::STATUSES)],
            'ratings' => ['array', 'required_if:status,completed'],
            'ratings.*.performance_indicator_id' => ['required', 'integer', 'distinct', Rule::exists('performance_indicators', 'id')],
            'ratings.*.rating' => ['required', 'numeric', 'between:1,5', 'decimal:0,1'],
            'ratings.*.comments' => ['nullable', 'string', 'max:1000'],
        ]);

        $ratings = [];

        foreach ($data['ratings'] ?? [] as $rating) {
            $ratings[(int) $rating['performance_indicator_id']] = ['rating' => $rating['rating'], 'comments' => $rating['comments'] ?? null];
        }

        unset($data['ratings']);
        $data['completion_date'] = $data['status'] === 'completed'
            ? ($employeeReview->completion_date ?? now()->toDateString())
            : null;

        return [$data, $ratings];
    }
}
