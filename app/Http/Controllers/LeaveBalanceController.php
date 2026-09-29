<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveBalanceAdjustment;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\LeaveBalances;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeaveBalanceController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $year = (int) $request->input('year', now()->year);
        $years = range(now()->year - 2, now()->year + 2);
        $year = in_array($year, $years, true) ? $year : now()->year;
        $search = trim($request->string('search')->toString());

        $query = $this->visibleEmployees($user)
            ->select(['id', 'user_id', 'employee_id', 'gender', 'created_at'])->with('user:id,name,avatar_path')
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->whereKey($id))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('employee_id', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))));

        $leaveTypes = LeaveType::query()->where('status', 'active')->orderBy('id')->get();
        $employees = TableQuery::paginate($query, $request, [], ['employee_id', 'created_at'], 'employee_id');
        $balances = LeaveBalances::for(array_map(fn (Employee $employee) => $employee->id, $employees->items()), $leaveTypes, $year);

        foreach ($employees->items() as $employee) {
            $employee->setAttribute('balances', array_values($balances[$employee->id]));
        }

        return Inertia::render('hr/leave-balances/index', [
            'employeeBalances' => $employees,
            'leaveTypes' => $leaveTypes->map->only(['id', 'name', 'color', 'max_days_per_year']),
            'year' => $year,
            'yearOptions' => $years,
            'employees' => $this->visibleEmployees($user)->with('user:id,name')->orderBy('employee_id')->get(['id', 'user_id', 'employee_id'])
                ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->user->name]),
            'filters' => TableQuery::filters($request, ['year', 'employee_id']),
        ]);
    }

    /**
     * Store the manual inputs of one balance; used and pending days always come from the applications.
     */
    public function adjust(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'carried_forward' => ['required', 'integer', 'min:0', 'max:366'],
            'manual_adjustment' => ['required', 'integer', 'between:-366,366'],
            'adjustment_reason' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless($this->visibleEmployees($user)->whereKey($data['employee_id'])->exists(), 404);

        LeaveBalanceAdjustment::query()->updateOrCreate(
            ['employee_id' => $data['employee_id'], 'leave_type_id' => $data['leave_type_id'], 'year' => $data['year']],
            $data,
        );

        return $this->done(__('Leave balance adjusted successfully.'));
    }

    /**
     * Every employee for manage-any-leave-balances; only the user's own for manage-own-leave-balances.
     *
     * @return Builder<Employee>
     */
    private function visibleEmployees(User $user): Builder
    {
        return Employee::query()->when(
            ! $user->can('manage-any-leave-balances'),
            fn ($q) => $q->where('user_id', $user->can('manage-own-leave-balances') ? $user->id : 0),
        );
    }
}
