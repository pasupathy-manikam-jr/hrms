<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use App\Models\Employee;
use App\Models\Promotion;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PromotionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        $query = Promotion::query()
            ->visibleTo($user)
            ->with('employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path', 'designation:id,name', 'previousDesignation:id,name', 'approver:id,name')
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->integer('designation_id'), fn ($q, $id) => $q->where('designation_id', $id))
            ->when($request->date('date_from'), fn ($q, $date) => $q->whereDate('promotion_date', '>=', $date))
            ->when($request->date('date_to'), fn ($q, $date) => $q->whereDate('promotion_date', '<=', $date))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('reason', 'like', "%{$search}%")
                ->orWhereHas('employee.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))));

        $counts = TableQuery::countBy($query, 'status');

        $query->when(in_array($request->input('status'), Promotion::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/promotions/index', [
            'promotions' => TableQuery::paginate($query, $request, [], ['promotion_date', 'effective_date', 'salary_adjustment', 'status', 'created_at']),
            'employees' => Promotion::employeeOptions($user),
            'designations' => Designation::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(Promotion::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['status', 'employee_id', 'designation_id', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        (new Promotion([...$this->validated($request), 'status' => 'pending', 'created_by' => $request->user()?->id]))
            ->attachUploadFrom($request)->save();

        return $this->done(__('Promotion created successfully.'));
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorizePending($request, $promotion);

        $promotion->fill($this->validated($request))->attachUploadFrom($request)->save();

        return $this->done(__('Promotion updated successfully.'));
    }

    public function destroy(Request $request, Promotion $promotion): RedirectResponse
    {
        abort_unless($promotion->isVisibleTo($this->user($request)), 404);

        $promotion->delete();

        return $this->done(__('Promotion deleted successfully.'));
    }

    /**
     * The demo's "Update Promotion Status" action. Approving moves the employee to the new designation.
     */
    public function changeStatus(Request $request, Promotion $promotion): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($promotion->isVisibleTo($user), 404);

        $status = $request->validate(['status' => ['required', Rule::in(Promotion::STATUSES)]])['status'];
        abort_unless($user->can($status === 'rejected' ? 'reject-promotions' : 'approve-promotions'), 403);
        abort_if($promotion->status === 'approved', 403, __('Approved promotions have already been applied.'));

        match ($status) {
            'approved' => $promotion->approve($user),
            'rejected' => $promotion->update(['status' => 'rejected', 'approved_by' => $user->id, 'approved_at' => now()]),
            default => $promotion->update(['status' => 'pending', 'approved_by' => null, 'approved_at' => null]),
        };

        return $this->done(__('Promotion status updated.'));
    }

    public function document(Request $request, Promotion $promotion): StreamedResponse
    {
        abort_unless($promotion->isVisibleTo($this->user($request)), 404);

        return $promotion->downloadUpload();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    private function authorizePending(Request $request, Promotion $promotion): void
    {
        abort_unless($promotion->isVisibleTo($this->user($request)), 404);
        abort_unless($promotion->status === 'pending', 403, __('Only pending promotions can be changed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'designation_id' => ['required', 'integer', Rule::exists('designations', 'id')],
            'promotion_date' => ['required', 'date_format:Y-m-d'],
            'effective_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:promotion_date'],
            'salary_adjustment' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'document' => Promotion::uploadRules(),
        ]);
        unset($data['document']);

        // "From" is always the employee's designation at the time the promotion is recorded.
        $data['previous_designation_id'] = Employee::query()->whereKey($data['employee_id'])->value('designation_id');

        return $data;
    }
}
