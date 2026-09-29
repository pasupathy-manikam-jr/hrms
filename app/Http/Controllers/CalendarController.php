<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\Meeting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Holidays, approved leave, meetings and birthdays the user may see, from the start of
     * last year to the end of next year. Months are switched in the browser (like the demo),
     * so navigation never needs a round trip or a query string.
     */
    public function index(#[CurrentUser] User $user): Response
    {
        $from = today()->toImmutable()->subYear()->startOfYear();
        $to = today()->toImmutable()->addYear()->endOfYear();

        return Inertia::render('calendar/index', [
            'events' => [
                ...$this->holidays($user, $from, $to),
                ...$this->leaves($user, $from, $to),
                ...$this->meetings($user, $from, $to),
                ...$this->birthdays($user, $from, $to),
            ],
        ]);
    }

    /**
     * Holidays for the user's branch (everyone's for users without one); recurring ones repeat every year.
     *
     * @return array<int, array<string, mixed>>
     */
    private function holidays(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! $user->can('manage-holidays')) {
            return [];
        }

        $query = Holiday::query()->visibleTo($user)->forBranch($user->employee?->branch_id);
        $events = [];

        foreach (Holiday::occurrences($query, $from, $to) as ['holiday' => $holiday, 'start' => $start, 'end' => $end]) {
            $events[] = $this->event("holiday_{$holiday->id}_{$start->year}", $holiday->name, 'holiday', $start->toDateString(), $end->toDateString(), [
                'category' => $holiday->category,
            ]);
        }

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function leaves(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! $user->can('manage-leave-applications')) {
            return [];
        }

        return LeaveApplication::query()
            ->visibleTo($user)
            ->with(['employee.user:id,name', 'leaveType:id,name'])
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get()
            ->map(fn (LeaveApplication $leave) => $this->event(
                "leave_{$leave->id}",
                $leave->employee->user?->name.' - '.$leave->leaveType->name,
                'leave',
                $leave->start_date->toDateString(),
                $leave->end_date->toDateString(),
            ))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function meetings(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! $user->can('manage-meetings')) {
            return [];
        }

        return Meeting::query()
            ->visibleTo($user)
            ->where('status', '!=', 'Cancelled')
            ->whereDate('meeting_date', '>=', $from)
            ->whereDate('meeting_date', '<=', $to)
            ->get()
            ->map(fn (Meeting $meeting) => $this->event(
                "meeting_{$meeting->id}",
                $meeting->title,
                'meeting',
                $meeting->meeting_date->toDateString(),
                $meeting->meeting_date->toDateString(),
                ['start_time' => $meeting->start_time, 'end_time' => $meeting->end_time, 'status' => $meeting->status],
            ))
            ->all();
    }

    /**
     * Each employee's birthday in every year of the range (29 Feb falls on 28 Feb in other years).
     *
     * @return array<int, array<string, mixed>>
     */
    private function birthdays(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! $user->can('manage-employees')) {
            return [];
        }

        $events = [];
        $employees = Employee::query()->visibleTo($user)
            ->where('employee_status', '!=', 'terminated')
            ->whereNotNull('date_of_birth')
            ->with('user:id,name')
            ->get();

        foreach ($employees as $employee) {
            $born = CarbonImmutable::parse($employee->date_of_birth);

            foreach (range($from->year, $to->year) as $year) {
                $day = CarbonImmutable::create($year, $born->month, min($born->day, CarbonImmutable::create($year, $born->month)->daysInMonth));
                $events[] = $this->event("birthday_{$employee->id}_{$year}", __(":name's Birthday", ['name' => $employee->user?->name]).' 🎉', 'birthday', $day->toDateString(), $day->toDateString());
            }
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function event(string $id, string $title, string $type, string $start, string $end, array $extra = []): array
    {
        return ['id' => $id, 'title' => $title, 'type' => $type, 'start' => $start, 'end' => $end, ...$extra];
    }
}
