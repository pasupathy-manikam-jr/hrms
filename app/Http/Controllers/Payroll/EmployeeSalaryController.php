<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\LeaveApplication;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeSalaryController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        $query = EmployeeSalary::query()
            ->visibleTo($user)
            ->with(['employee:id,user_id,employee_id,gender,date_of_birth,id_type,citizenship,marital_status,spouse_working,tax_children,tax_resident,lindung24_opt_out', 'employee.user:id,name,email,avatar_path', 'components'])
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($search, fn ($q) => $q->whereHas('employee', fn ($e) => $e
                ->where('employee_id', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))));

        $counts = TableQuery::countBy($query, 'is_active');
        $status = $request->input('status') ?? ($request->filled('is_active') ? ($request->boolean('is_active') ? 'active' : 'inactive') : null);
        $query->when(in_array($status, ['active', 'inactive'], true), fn ($q) => $q->where('is_active', $status === 'active'));

        $salaries = TableQuery::paginate($query, $request, [], ['basic_salary', 'created_at']);
        foreach ($salaries->items() as $salary) {
            $salary->forceFill(Arr::only($salary->calculate(), ['gross_pay', 'total_deductions', 'net_pay']));
        }

        return Inertia::render('hr/employee-salaries/index', [
            'employeeSalaries' => $salaries,
            'employees' => $user->can('manage-any-employee-salaries')
                ? Employee::query()->join('users', 'users.id', '=', 'employees.user_id')->orderBy('users.name')->get(['employees.id', 'users.name', 'employees.employee_id'])
                : [],
            'salaryComponents' => SalaryComponent::query()->where('status', 'active')->orderBy('type')->orderBy('id')
                ->get(['id', 'name', 'type', 'calculation_type', 'default_amount', 'percentage_of_basic']),
            'statusCounts' => ['all' => (int) $counts->sum(), 'active' => (int) ($counts[1] ?? 0), 'inactive' => (int) ($counts[0] ?? 0)],
            'filters' => TableQuery::filters($request, ['employee_id', 'is_active', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save(new EmployeeSalary, $this->validated($request));

        return $this->done(__('Employee salary created successfully.'));
    }

    public function update(Request $request, EmployeeSalary $employeeSalary): RedirectResponse
    {
        $this->save($employeeSalary, $this->validated($request, $employeeSalary));

        return $this->done(__('Employee salary updated successfully.'));
    }

    /**
     * The lock action: switch the salary on or off for payroll runs.
     */
    public function toggleStatus(Request $request, EmployeeSalary $employeeSalary): RedirectResponse
    {
        $this->authorizeVisible($request, $employeeSalary);
        $employeeSalary->update(['is_active' => ! $employeeSalary->is_active]);

        return $this->done(__('Employee salary status updated.'));
    }

    /**
     * The demo's "Payroll Calculation" page: one payroll run's payslip for this employee, with the
     * attendance behind it. Goes back with an error when the employee has never been paid.
     */
    public function payroll(Request $request, EmployeeSalary $employeeSalary): Response|RedirectResponse
    {
        $this->authorizeVisible($request, $employeeSalary);
        $employee = $employeeSalary->employee()->with('user:id,name')->firstOrFail();

        $runs = PayrollRun::query()
            ->whereHas('payslips', fn ($q) => $q->where('employee_id', $employee->id))
            ->orderByDesc('pay_period_start')
            ->get(['id', 'title', 'pay_period_start', 'pay_period_end', 'status']);

        if ($runs->isEmpty()) {
            return $this->toast('error', __('No payroll runs found for this employee.'));
        }

        /** @var PayrollRun $run */
        $run = $runs->firstWhere('id', $request->integer('payroll_run_id')) ?? $runs->first();
        $payslip = Payslip::query()->where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->firstOrFail();

        $records = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$run->pay_period_start->toDateString(), $run->pay_period_end->toDateString()])
            ->get(['status', 'overtime_hours']);
        $count = fn (string $status) => $records->where('status', $status)->count();

        return Inertia::render('hr/employee-salaries/payroll', [
            'employeeSalary' => $employeeSalary->only('id', 'basic_salary', 'is_active'),
            'employeeName' => $employee->user->name,
            'payrollRuns' => $runs,
            'selectedRunId' => $run->id,
            'payslip' => $payslip->only('basic_salary', 'total_earnings', 'gross_pay', 'total_deductions', 'net_pay', 'earnings', 'deductions', 'statutory'),
            'attendance' => [
                'working_days' => LeaveApplication::workingDaysBetween($run->pay_period_start, $run->pay_period_end, $employee->branch_id),
                'full_present_days' => $count('present'),
                'half_days' => $count('half_day'),
                'holiday_days' => $count('holiday'),
                'paid_leave_days' => $count('on_leave'),
                'absent_days' => $count('absent'),
                'overtime_hours' => round((float) $records->sum('overtime_hours'), 1),
                'present_days' => $count('present') + $count('holiday') + $count('on_leave') + $count('half_day') / 2,
            ],
        ]);
    }

    public function destroy(EmployeeSalary $employeeSalary): RedirectResponse
    {
        $employeeSalary->delete();

        return $this->done(__('Employee salary deleted successfully.'));
    }

    private function authorizeVisible(Request $request, EmployeeSalary $salary): void
    {
        abort_unless(EmployeeSalary::query()->visibleTo($request->user())->whereKey($salary->id)->exists(), 404);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(EmployeeSalary $salary, array $data): void
    {
        DB::transaction(function () use ($salary, $data) {
            $salary->fill(Arr::except($data, 'component_ids'))->save();
            $salary->components()->sync($data['component_ids'] ?? []);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?EmployeeSalary $salary = null): array
    {
        return $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id', Rule::unique('employee_salaries')->ignore($salary)],
            'basic_salary' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999999'],
            'component_ids' => ['array'],
            'component_ids.*' => ['integer', 'distinct', 'exists:salary_components,id'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'employee_id.unique' => __('This employee already has a salary.'),
        ]);
    }
}
