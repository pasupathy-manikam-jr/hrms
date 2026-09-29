<?php

namespace App\Http\Controllers;

use App\Models\AttendancePolicy;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendancePolicyController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AttendancePolicy::query()
            ->when(in_array($request->input('status'), AttendancePolicy::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/attendance-policies/index', [
            'attendancePolicies' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'late_arrival_grace', 'overtime_rate_per_hour', 'created_at']),
            'stats' => [
                'total' => AttendancePolicy::query()->count(),
                'active' => AttendancePolicy::query()->where('status', 'active')->count(),
                'avg_late_grace' => (int) round((float) AttendancePolicy::query()->avg('late_arrival_grace')),
                'avg_overtime_rate' => round((float) AttendancePolicy::query()->avg('overtime_rate_per_hour'), 2),
            ],
            'statusCounts' => [
                'all' => AttendancePolicy::query()->count(),
                'active' => AttendancePolicy::query()->where('status', 'active')->count(),
                'inactive' => AttendancePolicy::query()->where('status', 'inactive')->count(),
            ],
            'filters' => TableQuery::filters($request, ['status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AttendancePolicy::create($this->validated($request));

        return $this->done(__('Attendance policy created successfully.'));
    }

    public function update(Request $request, AttendancePolicy $attendancePolicy): RedirectResponse
    {
        $attendancePolicy->update($this->validated($request));

        return $this->done(__('Attendance policy updated successfully.'));
    }

    public function toggleStatus(AttendancePolicy $attendancePolicy): RedirectResponse
    {
        $attendancePolicy->update(['status' => $attendancePolicy->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Attendance policy status updated.'));
    }

    public function destroy(AttendancePolicy $attendancePolicy): RedirectResponse
    {
        $attendancePolicy->delete();

        return $this->done(__('Attendance policy deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'late_arrival_grace' => ['required', 'integer', 'min:0', 'max:1440'],
            'early_departure_grace' => ['required', 'integer', 'min:0', 'max:1440'],
            'half_day_threshold' => ['required', 'numeric', 'min:0', 'max:24'],
            'overtime_rate_per_hour' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'status' => ['required', Rule::in(AttendancePolicy::STATUSES)],
        ]);
    }
}
