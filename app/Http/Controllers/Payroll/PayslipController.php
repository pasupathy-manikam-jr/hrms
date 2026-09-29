<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use App\Support\TableQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayslipController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');
        $canSeeAll = $user->can('manage-any-payslips');
        // Like the demo, the list shows one pay period month at a time (this month by default);
        // a payroll run's "View payslips" link shows that whole run instead.
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->input('selected_month'))
            ? CarbonImmutable::createFromFormat('!Y-m', $request->input('selected_month'))
            : CarbonImmutable::now()->startOfMonth();
        $runId = $request->integer('payroll_run_id');

        $query = Payslip::query()
            ->visibleTo($user)
            ->with(['employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path', 'payrollRun:id,title,pay_period_start,pay_period_end,pay_date,status'])
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($runId, fn ($q, $id) => $q->where('payroll_run_id', $id))
            ->unless($runId, fn ($q) => $q->whereHas('payrollRun', fn ($r) => $r->whereBetween('pay_period_start', [$month->toDateString(), $month->endOfMonth()->toDateString()])))
            ->when($request->date('date_from'), fn ($q, $date) => $q->whereHas('payrollRun', fn ($r) => $r->whereDate('pay_date', '>=', $date)))
            ->when($request->date('date_to'), fn ($q, $date) => $q->whereHas('payrollRun', fn ($r) => $r->whereDate('pay_date', '<=', $date)))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('payslip_number', 'like', "%{$search}%")
                ->orWhereHas('employee.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))));

        $counts = TableQuery::countBy($query, 'status');
        $firstYear = (int) substr((string) (PayrollRun::query()->min('pay_period_start') ?? $month->toDateString()), 0, 4);
        // Sortable by the run's pay date, as in the demo.
        $query->select('payslips.*')->addSelect(['pay_date' => PayrollRun::query()->select('pay_date')->whereColumn('payroll_runs.id', 'payslips.payroll_run_id')]);
        $query->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));

        return Inertia::render('hr/payslips/index', [
            'payslips' => TableQuery::paginate($query, $request, [], ['pay_date', 'created_at']),
            'employees' => $canSeeAll
                ? Employee::query()->join('users', 'users.id', '=', 'employees.user_id')->orderBy('users.name')->get(['employees.id', 'users.name'])
                : [],
            'payrollRuns' => PayrollRun::query()
                ->unless($canSeeAll, fn ($q) => $q->where('status', 'completed'))
                ->orderByDesc('pay_period_start')->get(['id', 'title as name']),
            'years' => range(min($firstYear, $month->year), max($month->year, now()->year)),
            'selectedMonth' => $month->format('Y-m'),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(Payslip::TAB_STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['employee_id', 'payroll_run_id', 'status', 'date_from', 'date_to', 'selected_month']),
        ]);
    }
}
