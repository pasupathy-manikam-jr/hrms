<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Warning;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WarningController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        $query = Warning::query()
            ->visibleTo($user)
            ->with('employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path', 'issuer:id,name,email,avatar_path', 'approver:id,name')
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when(in_array($request->input('warning_type'), Warning::TYPES, true), fn ($q) => $q->where('warning_type', $request->input('warning_type')))
            ->when(in_array($request->input('severity'), Warning::SEVERITIES, true), fn ($q) => $q->where('severity', $request->input('severity')))
            ->when($request->date('date_from'), fn ($q, $date) => $q->whereDate('warning_date', '>=', $date))
            ->when($request->date('date_to'), fn ($q, $date) => $q->whereDate('warning_date', '<=', $date))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('subject', 'like', "%{$search}%")
                ->orWhereHas('employee.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))));

        $counts = TableQuery::countBy($query, 'status');

        $query->when(in_array($request->input('status'), Warning::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/warnings/index', [
            'warnings' => TableQuery::paginate($query, $request, [], ['warning_date', 'expiry_date', 'severity', 'status', 'created_at']),
            'employees' => Warning::employeeOptions($user),
            'managers' => $user->can('manage-any-warnings') ? User::role(Warning::ISSUER_ROLES)->orderBy('name')->get(['id', 'name']) : [],
            'warningTypes' => Warning::TYPES,
            'severities' => Warning::SEVERITIES,
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(Warning::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['status', 'employee_id', 'warning_type', 'severity', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $warning = new Warning([...$this->validated($request), 'status' => 'draft', 'created_by' => $request->user()?->id]);
        $warning->attachUploadFrom($request)->save();

        return $this->done(__('Warning created successfully.'));
    }

    public function update(Request $request, Warning $warning): RedirectResponse
    {
        $this->authorizeStatus($request, $warning, 'draft');

        $warning->fill($this->validated($request))->attachUploadFrom($request)->save();

        return $this->done(__('Warning updated successfully.'));
    }

    public function destroy(Request $request, Warning $warning): RedirectResponse
    {
        abort_unless($warning->isVisibleTo($this->user($request)), 404);

        $warning->delete();

        return $this->done(__('Warning deleted successfully.'));
    }

    /**
     * The demo's "change status" action. Acknowledging (with the employee's response) needs
     * acknowledge-warnings; every other move needs approve-warnings.
     */
    public function changeStatus(Request $request, Warning $warning): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($warning->isVisibleTo($user), 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(Warning::STATUSES)],
            'acknowledgment_date' => ['nullable', 'required_if:status,acknowledged', 'date_format:Y-m-d'],
            'employee_response' => ['nullable', 'required_if:status,acknowledged', 'string', 'max:2000'],
        ]);
        abort_unless($user->can($data['status'] === 'acknowledged' ? 'acknowledge-warnings' : 'approve-warnings'), 403);

        $warning->update(match ($data['status']) {
            'acknowledged' => Arr::only($data, ['status', 'acknowledgment_date', 'employee_response']),
            'issued' => ['status' => 'issued', 'approved_by' => $user->id, 'approved_at' => now()],
            default => ['status' => $data['status']],
        });

        return $this->done(__('Warning status updated.'));
    }

    /**
     * The demo's "update improvement plan" action: switch the plan on or off, set its goals and dates, and record progress.
     */
    public function improvementPlan(Request $request, Warning $warning): RedirectResponse
    {
        abort_unless($warning->isVisibleTo($this->user($request)), 404);

        $data = $request->validate([
            'has_improvement_plan' => ['boolean'],
            'improvement_plan_goals' => ['nullable', 'required_if:has_improvement_plan,true', 'string', 'max:2000'],
            'improvement_plan_start_date' => ['nullable', 'required_if:has_improvement_plan,true', 'date_format:Y-m-d'],
            'improvement_plan_end_date' => ['nullable', 'required_if:has_improvement_plan,true', 'date_format:Y-m-d', 'after:improvement_plan_start_date'],
            'improvement_plan_progress' => ['nullable', 'string', 'max:2000'],
        ]) + ['has_improvement_plan' => false];

        $warning->update($data['has_improvement_plan'] ? $data : [
            'has_improvement_plan' => false, 'improvement_plan_goals' => null, 'improvement_plan_start_date' => null,
            'improvement_plan_end_date' => null, 'improvement_plan_progress' => null,
        ]);

        return $this->done(__('Improvement plan updated.'));
    }

    /**
     * Stream the supporting document to someone allowed to see the warning.
     */
    public function document(Request $request, Warning $warning): StreamedResponse
    {
        abort_unless($warning->isVisibleTo($this->user($request)), 404);

        return $warning->downloadUpload();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    private function authorizeStatus(Request $request, Warning $warning, string $status): void
    {
        abort_unless($warning->isVisibleTo($this->user($request)), 404);
        abort_unless($warning->status === $status, 403, __('This warning can no longer be changed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $issuers = User::role(Warning::ISSUER_ROLES)->pluck('id')->all();

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'warning_by' => ['required', 'integer', Rule::in($issuers)],
            'warning_type' => ['required', Rule::in(Warning::TYPES)],
            'subject' => ['required', 'string', 'max:255'],
            'severity' => ['required', Rule::in(Warning::SEVERITIES)],
            'warning_date' => ['required', 'date_format:Y-m-d'],
            'expiry_date' => ['nullable', 'date_format:Y-m-d', 'after:warning_date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'has_improvement_plan' => ['boolean'],
            'improvement_plan_goals' => ['nullable', 'required_if:has_improvement_plan,true', 'string', 'max:2000'],
            'improvement_plan_start_date' => ['nullable', 'required_if:has_improvement_plan,true', 'date_format:Y-m-d'],
            'improvement_plan_end_date' => ['nullable', 'required_if:has_improvement_plan,true', 'date_format:Y-m-d', 'after:improvement_plan_start_date'],
            'document' => Warning::uploadRules(),
        ]);
        $data = Arr::except($data, 'document') + ['has_improvement_plan' => false];

        // Switching the plan off clears it.
        return $data['has_improvement_plan'] ? $data : array_merge($data, [
            'improvement_plan_goals' => null, 'improvement_plan_start_date' => null, 'improvement_plan_end_date' => null,
        ]);
    }
}
