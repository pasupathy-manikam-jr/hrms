<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\EmployeeContract;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeContractController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        $query = EmployeeContract::query()
            ->visibleTo($user)
            ->with('employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path', 'contractType:id,name', 'contractTemplate:id,name')
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->integer('contract_type_id'), fn ($q, $id) => $q->where('contract_type_id', $id))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('contract_number', 'like', "%{$search}%")
                ->orWhereHas('employee.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))));

        $statusCounts = ['all' => (clone $query)->count()]
            + collect(EmployeeContract::STATUSES)->mapWithKeys(fn ($s) => [$s => (clone $query)->whereStatus($s)->count()])->all();

        $query->when(in_array($request->input('status'), EmployeeContract::STATUSES, true), fn ($q) => $q->whereStatus($request->input('status')));

        return Inertia::render('hr/contracts/employee-contracts/index', [
            'employeeContracts' => TableQuery::paginate($query, $request, [], ['contract_number', 'start_date', 'end_date', 'basic_salary', 'status', 'created_at']),
            'statusCounts' => $statusCounts,
            'stats' => [
                'active' => $statusCounts['active'],
                'near_expiry' => EmployeeContract::query()->visibleTo($user)->whereStatus('active')->whereDate('end_date', '<=', today()->addDays(30))->count(),
                'draft' => $statusCounts['draft'],
            ],
            'employees' => EmployeeContract::employeeOptions($user),
            'contractTypes' => ContractType::query()->orderBy('name')->get(['id', 'name']),
            'contractTemplates' => ContractTemplate::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'contract_type_id']),
            'statuses' => EmployeeContract::STORED_STATUSES,
            'filters' => TableQuery::filters($request, ['status', 'employee_id', 'contract_type_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        EmployeeContract::create([
            ...$data,
            'contract_number' => $data['contract_number'] ?? EmployeeContract::nextNumber(),
            'created_by' => $request->user()?->id,
        ]);

        return $this->done(__('Employee contract created successfully.'));
    }

    public function update(Request $request, EmployeeContract $employeeContract): RedirectResponse
    {
        abort_unless($employeeContract->isVisibleTo($this->user($request)), 404);

        $data = $this->validated($request, $employeeContract);
        $employeeContract->update([...$data, 'contract_number' => $data['contract_number'] ?? $employeeContract->contract_number]);

        return $this->done(__('Employee contract updated successfully.'));
    }

    /**
     * The demo's "Update Contract Status" action.
     */
    public function changeStatus(Request $request, EmployeeContract $employeeContract): RedirectResponse
    {
        abort_unless($employeeContract->isVisibleTo($this->user($request)), 404);
        $employeeContract->update($request->validate(['status' => ['required', Rule::in(EmployeeContract::STORED_STATUSES)]]));

        return $this->done(__('Contract status updated successfully.'));
    }

    public function destroy(Request $request, EmployeeContract $employeeContract): RedirectResponse
    {
        abort_unless($employeeContract->isVisibleTo($this->user($request)), 404);
        $employeeContract->delete();

        return $this->done(__('Employee contract deleted successfully.'));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?EmployeeContract $contract = null): array
    {
        return $request->validate([
            'contract_number' => ['nullable', 'string', 'max:50', Rule::unique('employee_contracts')->ignore($contract)],
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'contract_type_id' => ['required', 'integer', Rule::exists('contract_types', 'id')],
            'contract_template_id' => ['nullable', 'integer', Rule::exists('contract_templates', 'id')],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after:start_date'],
            'basic_salary' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'terms_conditions' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(EmployeeContract::STORED_STATUSES)],
        ]);
    }
}
