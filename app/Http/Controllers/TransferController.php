<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Transfer;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransferController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        $query = Transfer::query()
            ->visibleTo($user)
            ->with(
                'employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path', 'approver:id,name',
                'fromBranch:id,name', 'toBranch:id,name', 'fromDepartment:id,name', 'toDepartment:id,name',
                'fromDesignation:id,name', 'toDesignation:id,name',
            )
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->integer('branch_id'), fn ($q, $id) => $q->where(fn ($q) => $q->where('from_branch_id', $id)->orWhere('to_branch_id', $id)))
            ->when($request->integer('department_id'), fn ($q, $id) => $q->where(fn ($q) => $q->where('from_department_id', $id)->orWhere('to_department_id', $id)))
            ->when($request->date('date_from'), fn ($q, $date) => $q->whereDate('transfer_date', '>=', $date))
            ->when($request->date('date_to'), fn ($q, $date) => $q->whereDate('transfer_date', '<=', $date))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('reason', 'like', "%{$search}%")
                ->orWhereHas('employee.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))));

        $counts = TableQuery::countBy($query, 'status');

        $query->when(in_array($request->input('status'), Transfer::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/transfers/index', [
            'transfers' => TableQuery::paginate($query, $request, [], ['transfer_date', 'effective_date', 'status', 'created_at']),
            'employees' => Transfer::employeeOptions($user),
            'branches' => Branch::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'branch_id']),
            'designations' => Designation::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'department_id']),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(Transfer::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['status', 'employee_id', 'branch_id', 'department_id', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        (new Transfer([...$this->validated($request), 'status' => 'pending', 'created_by' => $request->user()?->id]))
            ->attachUploadFrom($request)->save();

        return $this->done(__('Transfer created successfully.'));
    }

    public function update(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorizePending($request, $transfer);

        $transfer->fill($this->validated($request))->attachUploadFrom($request)->save();

        return $this->done(__('Transfer updated successfully.'));
    }

    public function destroy(Request $request, Transfer $transfer): RedirectResponse
    {
        abort_unless($transfer->isVisibleTo($this->user($request)), 404);

        $transfer->delete();

        return $this->done(__('Transfer deleted successfully.'));
    }

    public function approve(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorizePending($request, $transfer);
        $notes = $request->validate(['notes' => ['nullable', 'string', 'max:1000']])['notes'] ?? null;

        $transfer->approve($this->user($request));
        $notes === null || $transfer->update(['notes' => $notes]);

        return $this->done(__('Transfer approved and applied to the employee.'));
    }

    public function reject(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorizePending($request, $transfer);
        // The demo records why a transfer was turned down.
        $data = $request->validate(['notes' => ['required', 'string', 'max:1000']]);

        $transfer->update([...$data, 'status' => 'rejected', 'approved_by' => $request->user()?->id, 'approved_at' => now()]);

        return $this->done(__('Transfer rejected.'));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    private function authorizePending(Request $request, Transfer $transfer): void
    {
        abort_unless($transfer->isVisibleTo($this->user($request)), 404);
        abort_unless($transfer->status === 'pending', 403, __('Only pending transfers can be changed.'));
    }

    /**
     * Stream the transfer's supporting document to someone allowed to see the transfer.
     */
    public function document(Request $request, Transfer $transfer): StreamedResponse
    {
        abort_unless($transfer->isVisibleTo($this->user($request)), 404);

        return $transfer->downloadUpload();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'to_branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'to_department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('branch_id', $request->integer('to_branch_id'))],
            'to_designation_id' => ['required', 'integer', Rule::exists('designations', 'id')->where('department_id', $request->integer('to_department_id'))],
            'transfer_date' => ['required', 'date_format:Y-m-d'],
            'effective_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:transfer_date'],
            'reason' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'document' => Transfer::uploadRules(),
        ]);
        unset($data['document']);

        // "From" is always where the employee sits when the transfer is recorded.
        $employee = Employee::query()->whereKey($data['employee_id'])->firstOrFail();

        return [
            ...$data,
            'from_branch_id' => $employee->branch_id,
            'from_department_id' => $employee->department_id,
            'from_designation_id' => $employee->designation_id,
        ];
    }
}
