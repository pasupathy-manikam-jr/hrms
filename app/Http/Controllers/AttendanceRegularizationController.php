<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceRegularization;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceRegularizationController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        $query = AttendanceRegularization::query()
            ->visibleTo($user)
            ->with('employee:id,user_id,employee_id,gender', 'employee.user:id,name,avatar_path', 'approver:id,name')
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->date('date_from'), fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($request->date('date_to'), fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('reason', 'like', "%{$search}%")
                ->orWhereHas('employee.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))));

        $counts = TableQuery::countBy($query, 'status');

        $query->when(in_array($request->input('status'), AttendanceRegularization::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/attendance-regularizations/index', [
            'regularizations' => TableQuery::paginate($query, $request, [], ['date', 'status', 'created_at']),
            'employees' => AttendanceRegularization::employeeOptions($user),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(AttendanceRegularization::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['status', 'employee_id', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AttendanceRegularization::create([...$this->validated($request), 'status' => 'pending']);

        return $this->done(__('Regularization request submitted successfully.'));
    }

    public function update(Request $request, AttendanceRegularization $attendanceRegularization): RedirectResponse
    {
        $this->authorizeChange($request, $attendanceRegularization);

        $attendanceRegularization->update($this->validated($request, $attendanceRegularization));

        return $this->done(__('Regularization request updated successfully.'));
    }

    public function destroy(Request $request, AttendanceRegularization $attendanceRegularization): RedirectResponse
    {
        $this->authorizeChange($request, $attendanceRegularization);

        $attendanceRegularization->delete();

        return $this->done(__('Regularization request deleted successfully.'));
    }

    /**
     * Apply the requested times to that day's attendance record (creating it if missing).
     */
    public function approve(Request $request, AttendanceRegularization $attendanceRegularization): RedirectResponse
    {
        $data = $this->decision($request, $attendanceRegularization);

        DB::transaction(function () use ($attendanceRegularization, $data) {
            $record = AttendanceRecord::recordClockTimes(
                $attendanceRegularization->employee,
                $attendanceRegularization->date->toDateString(),
                substr($attendanceRegularization->requested_clock_in, 0, 5),
                $attendanceRegularization->requested_clock_out ? substr($attendanceRegularization->requested_clock_out, 0, 5) : null,
            );

            $attendanceRegularization->update([...$data, 'status' => 'approved', 'attendance_record_id' => $record->id]);
        });

        return $this->done(__('Regularization approved and attendance updated.'));
    }

    public function reject(Request $request, AttendanceRegularization $attendanceRegularization): RedirectResponse
    {
        $attendanceRegularization->update([...$this->decision($request, $attendanceRegularization), 'status' => 'rejected']);

        return $this->done(__('Regularization rejected.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function decision(Request $request, AttendanceRegularization $regularization): array
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($regularization->isVisibleTo($user), 404);

        $data = $request->validate(['manager_comments' => ['nullable', 'string', 'max:1000']]);

        if ($regularization->status !== 'pending') {
            throw ValidationException::withMessages(['status' => __('This request has already been decided.')]);
        }

        return [...$data, 'approved_by' => $user->id, 'approved_at' => now()];
    }

    /**
     * Only pending requests the user can see may be changed.
     */
    private function authorizeChange(Request $request, AttendanceRegularization $regularization): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($regularization->isVisibleTo($user), 404);
        abort_unless($regularization->status === 'pending', 403, __('Only pending requests can be changed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AttendanceRegularization $regularization = null): array
    {
        /** @var User $user */
        $user = $request->user();
        $canActForOthers = $user->can('manage-any-attendance-regularizations');

        $data = $request->validate([
            'employee_id' => [Rule::requiredIf($canActForOthers), 'integer', Rule::exists('employees', 'id')],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.AttendanceRecord::now()->toDateString()],
            'requested_clock_in' => ['required', 'date_format:H:i'],
            'requested_clock_out' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        // Employees only request corrections of their own attendance.
        if (! $canActForOthers) {
            $data['employee_id'] = $regularization->employee_id ?? $user->employee()->value('id')
                ?? throw ValidationException::withMessages(['employee_id' => __('Your account has no employee profile.')]);
        }

        $pendingExists = AttendanceRegularization::query()
            ->where('employee_id', $data['employee_id'])
            ->whereDate('date', $data['date'])
            ->where('status', 'pending')
            ->when($regularization, fn ($q) => $q->whereKeyNot($regularization->id))
            ->exists();

        if ($pendingExists) {
            throw ValidationException::withMessages(['date' => __('A pending request for this day already exists.')]);
        }

        // Snapshot the day as it is now, so the reviewer sees what changes.
        $record = AttendanceRecord::query()->where('employee_id', $data['employee_id'])->whereDate('date', $data['date'])->first();

        return [
            ...$data,
            'attendance_record_id' => $record?->id,
            'original_clock_in' => $record?->clock_in,
            'original_clock_out' => $record?->clock_out,
        ];
    }
}
