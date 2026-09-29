<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\Asset;
use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosting;
use App\Models\LeaveApplication;
use App\Models\Meeting;
use App\Models\PayrollRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Live figures for the company and employee dashboards (same shape the pages render).
 */
class Dashboard
{
    private const CANDIDATE_COLORS = [
        'New' => '#0EA5E9', 'Screening' => '#F59E0B', 'Interview' => '#8B5CF6',
        'Offer' => '#14B8A6', 'Hired' => '#10B981', 'Rejected' => '#EF4444',
    ];

    private const LEAVE_COLORS = ['approved' => '#10B981', 'pending' => '#F59E0B', 'rejected' => '#EF4444'];

    /**
     * @return array<string, mixed>
     */
    public static function company(User $user, ?int $hiringYear, ?int $payrollYear): array
    {
        $today = AttendanceRecord::now()->startOfDay();
        $monthStart = $today->startOfMonth();
        $years = self::availableYears($today->year);
        $hiringYear = in_array($hiringYear, $years, true) ? $hiringYear : $today->year;
        $payrollYear = in_array($payrollYear, $years, true) ? $payrollYear : $today->year;

        $activeEmployees = Employee::query()->where('employee_status', '!=', 'terminated');
        $todayRecords = AttendanceRecord::query()->whereDate('date', $today);
        $monthRecords = AttendanceRecord::query()->whereBetween('date', [$monthStart, $today]);
        $onLeaveToday = self::onLeaveOn($today);
        $monthRuns = PayrollRun::query()->where('status', 'completed')
            ->whereBetween('pay_period_start', [$monthStart, $today->endOfMonth()]);
        $leaveThisYear = LeaveApplication::query()->whereYear('start_date', $today->year);

        return [
            'stats' => [
                'totalEmployees' => (clone $activeEmployees)->count(),
                'newEmployeesThisMonth' => Employee::query()->whereBetween('date_of_joining', [$monthStart, $today])->count(),
                'totalBranches' => Branch::query()->count(),
                'totalDepartments' => Department::query()->count(),
                'attendanceRate' => self::attendanceRate($monthRecords),
                'presentToday' => (clone $todayRecords)->whereIn('status', ['present', 'half_day'])->count(),
                'pendingLeaves' => LeaveApplication::query()->where('status', 'pending')->count(),
                'onLeaveToday' => $onLeaveToday->count(),
                'activeJobPostings' => JobPosting::query()->where('status', 'Published')->count(),
                'jobPostsThisMonth' => JobPosting::query()->where('created_at', '>=', $monthStart)->count(),
                'totalPayrollThisMonth' => round((float) (clone $monthRuns)->sum('total_net_pay'), 2),
                'payrollRunsThisMonth' => (clone $monthRuns)->count(),
            ],
            'charts' => [
                'attendanceWeekly' => self::attendanceWeekly($today),
                'leaveOverview' => self::countsBy($leaveThisYear, 'status', self::LEAVE_COLORS),
                'hiringTrend' => self::hiringTrend($hiringYear),
                'hiringYear' => $hiringYear,
                'payrollTrend' => PayrollRun::monthlyNetPay($payrollYear),
                'payrollYear' => $payrollYear,
                'availableYears' => $years,
                'assetStatusStats' => Asset::statusStats(),
                'candidateStatusStats' => self::countsBy(Candidate::query(), 'status', self::CANDIDATE_COLORS),
            ],
            'recentActivities' => [
                'leaves' => LeaveApplication::query()->with(['employee.user:id,name', 'leaveType:id,name'])
                    ->latest()->limit(5)->get()
                    ->map(fn (LeaveApplication $leave) => [
                        'id' => $leave->id,
                        'employee' => $leave->employee?->user?->name,
                        'leave_type' => $leave->leaveType?->name,
                        'start_date' => $leave->start_date->toDateString(),
                        'status' => $leave->status,
                    ]),
                'candidates' => Candidate::query()->with('job:id,title')
                    ->latest('application_date')->latest('id')->limit(5)->get()
                    ->map(fn (Candidate $candidate) => [
                        'id' => $candidate->id,
                        'name' => trim($candidate->first_name.' '.$candidate->last_name),
                        'job' => $candidate->job?->title,
                        'application_date' => $candidate->application_date?->toDateString(),
                        'status' => $candidate->status,
                    ]),
                'announcements' => self::announcements($user),
                'meetings' => self::upcomingMeetings($user, $today),
            ],
            'todayBirthdays' => self::birthdays($today),
            'todayOnLeave' => $onLeaveToday->map(fn (LeaveApplication $leave) => [
                'id' => $leave->id,
                'name' => $leave->employee?->user?->name,
                'designation' => $leave->employee?->designation?->name,
                'leaveType' => $leave->leaveType?->name,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function employee(User $user): array
    {
        $today = AttendanceRecord::now()->startOfDay();
        $employee = $user->employee;

        return [
            'stats' => [
                'totalAwards' => self::countFor('App\Models\Award', $employee),
                'totalWarnings' => self::countFor('App\Models\Warning', $employee),
                'totalComplaints' => self::countFor('App\Models\Complaint', $employee, 'against_employee_id'),
            ],
            'recentActivities' => [
                'announcements' => self::announcements($user),
                'meetings' => self::upcomingMeetings($user, $today),
            ],
        ];
    }

    /**
     * Records a Lifecycle module keeps about an employee; 0 until that module exists.
     */
    private static function countFor(string $model, ?Employee $employee, string $column = 'employee_id'): int
    {
        if (! $employee || ! class_exists($model)) {
            return 0;
        }

        /** @var class-string<Model> $model */
        $table = (new $model)->getTable();

        return Schema::hasColumn($table, $column)
            ? $model::query()->where($column, $employee->id)->count()
            : 0;
    }

    /**
     * @return list<int>
     */
    private static function availableYears(int $currentYear): array
    {
        $joined = Employee::query()->whereNotNull('date_of_joining')->get(['date_of_joining'])->map(fn (Employee $employee): int => $employee->date_of_joining->year)->all();
        $paid = PayrollRun::query()->get(['pay_period_start'])->map(fn (PayrollRun $run): int => $run->pay_period_start->year)->all();
        $years = array_values(array_unique([$currentYear, ...$joined, ...$paid]));
        rsort($years);

        return $years;
    }

    /**
     * Share of this month's attendance records that were present (half days count half).
     *
     * @param  Builder<AttendanceRecord>  $records
     */
    private static function attendanceRate(Builder $records): float
    {
        $counts = (clone $records)->whereIn('status', ['present', 'half_day', 'absent'])
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $days = $counts->sum();

        return $days === 0 ? 0.0 : round(($counts->get('present', 0) + $counts->get('half_day', 0) / 2) / $days * 100, 1);
    }

    /**
     * @return Collection<int, array{day: string, present: int, absent: int, leave: int}>
     */
    private static function attendanceWeekly(CarbonImmutable $today): Collection
    {
        $from = $today->subDays(6);
        $rows = AttendanceRecord::query()->whereBetween('date', [$from, $today])
            ->get(['date', 'status'])
            ->groupBy(fn (AttendanceRecord $record) => $record->date->toDateString());

        return collect(range(0, 6))->map(function (int $offset) use ($from, $rows) {
            $day = $from->addDays($offset);
            $records = $rows->get($day->toDateString()) ?? collect();

            return [
                'day' => $day->format('D'),
                'present' => $records->whereIn('status', ['present', 'half_day'])->count(),
                'absent' => $records->where('status', 'absent')->count(),
                'leave' => $records->where('status', 'on_leave')->count(),
            ];
        });
    }

    /**
     * @return list<array{short: string, month: string, hires: int}>
     */
    private static function hiringTrend(int $year): array
    {
        $hires = Employee::query()->whereYear('date_of_joining', $year)->pluck('date_of_joining')
            ->countBy(fn ($date) => $date->month);

        return array_map(fn (int $month) => [
            'short' => CarbonImmutable::create($year, $month)->format('M'),
            'month' => CarbonImmutable::create($year, $month)->format('F Y'),
            'hires' => $hires->get($month, 0),
        ], range(1, 12));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, string>  $colors  value => colour, in display order
     * @return list<array{name: string, value: int, color: string}>
     */
    private static function countsBy(Builder $query, string $column, array $colors): array
    {
        $counts = $query->toBase()->select("{$column} as bucket")->selectRaw('count(*) as total')->groupBy($column)->pluck('total', 'bucket');

        return array_map(fn (string $value, string $color) => [
            'name' => ucfirst($value),
            'value' => (int) $counts->get($value, 0),
            'color' => $color,
        ], array_keys($colors), $colors);
    }

    /**
     * @return Collection<int, LeaveApplication>
     */
    private static function onLeaveOn(CarbonImmutable $day): Collection
    {
        return LeaveApplication::query()->with(['employee.user:id,name', 'employee.designation:id,name', 'leaveType:id,name'])
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $day)->whereDate('end_date', '>=', $day)
            ->get();
    }

    /**
     * @return Collection<int, array{id: int, name: string|null, designation: string|null}>
     */
    private static function birthdays(CarbonImmutable $today): Collection
    {
        return Employee::query()->with(['user:id,name', 'designation:id,name'])
            ->where('employee_status', '!=', 'terminated')
            ->whereMonth('date_of_birth', $today->month)->whereDay('date_of_birth', $today->day)
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->user?->name,
                'designation' => $employee->designation?->name,
            ]);
    }

    /**
     * @return Collection<int, array{id: int, title: string, category: string, start_date: string, is_high_priority: bool}>
     */
    private static function announcements(User $user): Collection
    {
        return Announcement::query()->visibleTo($user)->latest('start_date')->limit(5)->get()
            ->map(fn (Announcement $announcement) => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'category' => $announcement->category,
                'start_date' => $announcement->start_date->toDateString(),
                'is_high_priority' => $announcement->is_high_priority,
            ]);
    }

    /**
     * @return Collection<int, array{id: int, title: string, meeting_date: string, start_time: string, end_time: string, status: string}>
     */
    private static function upcomingMeetings(User $user, CarbonImmutable $today): Collection
    {
        return Meeting::query()->visibleTo($user)
            ->whereDate('meeting_date', '>=', $today)
            ->where('status', '!=', 'Cancelled')
            ->orderBy('meeting_date')->orderBy('start_time')->limit(5)->get()
            ->map(fn (Meeting $meeting) => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'meeting_date' => $meeting->meeting_date->toDateString(),
                'start_time' => $meeting->start_time,
                'end_time' => $meeting->end_time,
                'status' => $meeting->status,
            ]);
    }
}
