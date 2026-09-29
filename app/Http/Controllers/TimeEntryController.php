<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Setting;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Csv;
use App\Support\TableQuery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimeEntryController extends Controller
{
    /**
     * The demo's weekly timesheet: one row per employee, a cell per day (hours, entries, status)
     * and a weekly total. The week's entries come along so a cell can open that day's entries.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $request->validate(['week_start' => ['nullable', 'date_format:Y-m-d']]);

        $weekStart = ($request->date('week_start') ?? today())->toImmutable()->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->addDays(6);

        $query = $this->filteredQuery($request);
        $counts = TableQuery::countBy($query, 'status');
        $this->applyStatus($query, $request);

        $entries = (clone $query)->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('date')->orderBy('id')->get();

        $search = trim($request->string('search')->toString());
        // ponytail: every visible employee is one row (no paging); fine for company-sized headcounts.
        $employees = Employee::query()->visibleTo($user)
            ->with('user:id,name,email,avatar_path', 'designation:id,name')
            ->where('employee_status', '!=', 'terminated')
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->whereKey($id))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
                ->orWhereIn('id', $entries->pluck('employee_id'))))
            ->orderBy('id') // the order people were added, like the demo
            ->get();

        return Inertia::render('hr/time-entries/index', [
            'weekStart' => $weekStart->toDateString(),
            'rows' => $employees->map(fn (Employee $employee) => [
                'employee' => [
                    'id' => $employee->id, 'employee_id' => $employee->employee_id, 'gender' => $employee->gender,
                    'name' => $employee->user->name, 'avatar' => $employee->user->avatar, 'designation' => $employee->designation?->name,
                ],
                'days' => $entries->where('employee_id', $employee->id)
                    ->groupBy(fn (TimeEntry $entry) => $entry->date->toDateString())
                    ->map(fn ($day) => [
                        'hours' => round((float) $day->sum('hours'), 2),
                        'entries' => $day->count(),
                        'status' => TimeEntry::summaryStatus($day->pluck('status')->all()),
                    ]),
                'total' => round((float) $entries->where('employee_id', $employee->id)->sum('hours'), 2),
            ])->values(),
            'entries' => $entries,
            'employees' => TimeEntry::employeeOptions($user),
            'projects' => TimeEntry::PROJECTS,
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(TimeEntry::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all(),
            'workingDays' => Setting::get('workingDays'),
            'filters' => TableQuery::filters($request, ['status', 'employee_id', 'project', 'date_from', 'date_to', 'week_start']),
        ]);
    }

    /** CSV columns shared by the template and the import. */
    private const CSV_COLUMNS = ['Employee ID' => 'employee_id', 'Date' => 'date', 'Hours' => 'hours', 'Project' => 'project', 'Description' => 'description', 'Billable' => 'is_billable'];

    public function export(Request $request): StreamedResponse
    {
        $entries = $this->applyStatus($this->filteredQuery($request), $request)->orderByDesc('date')->orderBy('id')->lazy();

        return Csv::download('time-entries-'.now()->format('Y-m-d').'.csv', [
            'Employee ID', 'Employee', 'Date', 'Hours', 'Project', 'Description', 'Billable', 'Status', 'Approved By', 'Approved At', 'Submitted On',
        ], $entries->map(fn (TimeEntry $e) => [
            $e->employee->employee_id, $e->employee->user->name, $e->date->toDateString(), $e->hours, $e->project, $e->description,
            $e->is_billable ? 'Yes' : 'No', $e->status, $e->approver?->name, $e->approved_at?->toDateTimeString(), $e->created_at?->toDateTimeString(),
        ]));
    }

    public function template(Request $request): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        return Csv::download('time-entries-import-template.csv', array_keys(self::CSV_COLUMNS), [[
            $user->can('manage-any-time-entries') ? Employee::query()->orderBy('id')->value('employee_id') : $user->employee?->employee_id,
            now()->toDateString(), '8', TimeEntry::PROJECTS[0], 'Worked on frontend components and user interface improvements', 'Yes',
        ]]);
    }

    /**
     * Import entries from the template's CSV through the form's rules (including the 24-hour
     * daily cap); bad rows are skipped and reported by row number.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => Csv::uploadRules()]);

        /** @var User $user */
        $user = $request->user();
        $employees = Employee::query()->pluck('id', 'employee_id')->mapWithKeys(fn ($id, $code) => [mb_strtolower($code) => $id]);
        $imported = 0;
        $skipped = [];

        foreach (Csv::read($request->file('file')) as $line => $row) {
            $input = collect(self::CSV_COLUMNS)->mapWithKeys(fn ($field, $header) => [$field => ($row[Str::snake($header)] ?? '') === '' ? null : $row[Str::snake($header)]])->all();
            $code = mb_strtolower((string) $input['employee_id']);
            $input['employee_id'] = $employees[$code] ?? ($code === '' ? null : 0);
            $input['is_billable'] = in_array(mb_strtolower((string) $input['is_billable']), ['1', 'yes', 'true', 'y'], true);

            try {
                TimeEntry::create([...$this->validateInput($user, $input), 'status' => 'pending']);
                $imported++;
            } catch (ValidationException $e) {
                $skipped[] = ['row' => $line, 'errors' => $e->validator->errors()->all()];
            }
        }

        Inertia::flash('import', ['imported' => $imported, 'skipped' => $skipped]);

        return $skipped === []
            ? $this->done(__(':count time entries imported.', ['count' => $imported]))
            : $this->toast('error', __(':imported imported, :skipped skipped. See the import report.', ['imported' => $imported, 'skipped' => count($skipped)]));
    }

    public function store(Request $request): RedirectResponse
    {
        TimeEntry::create([...$this->validated($request), 'status' => 'pending']);

        return $this->done(__('Time entry created successfully.'));
    }

    public function update(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        $this->authorizeChange($request, $timeEntry);

        // An edited entry goes back for approval.
        $timeEntry->update([...$this->validated($request, $timeEntry), 'status' => 'pending', 'approved_by' => null, 'approved_at' => null]);

        return $this->done(__('Time entry updated successfully.'));
    }

    public function destroy(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        $this->authorizeChange($request, $timeEntry);

        $timeEntry->delete();

        return $this->done(__('Time entry deleted successfully.'));
    }

    public function approve(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        return $this->decide($request, $timeEntry, 'approved', __('Time entry approved.'));
    }

    public function reject(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        return $this->decide($request, $timeEntry, 'rejected', __('Time entry rejected.'));
    }

    /**
     * Only pending entries are decided on.
     */
    private function decide(Request $request, TimeEntry $timeEntry, string $status, string $message): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($timeEntry->isVisibleTo($user), 404);

        $data = $request->validate(['manager_comments' => ['nullable', 'string', 'max:1000']]);

        if ($timeEntry->status !== 'pending') {
            throw ValidationException::withMessages(['status' => __('Only pending time entries can be approved or rejected.')]);
        }

        $timeEntry->update([...$data, 'status' => $status, 'approved_by' => $user->id, 'approved_at' => now()]);

        return $this->done($message);
    }

    /**
     * Staff may change any visible entry; employees only their own entries that are not yet approved.
     */
    private function authorizeChange(Request $request, TimeEntry $timeEntry): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($timeEntry->isVisibleTo($user), 404);
        abort_unless($user->can('manage-any-time-entries') || $timeEntry->status !== 'approved', 403, __('Approved time entries can no longer be changed.'));
    }

    /**
     * The list's visible entries with its employee/project/date filters and search (before the status tab).
     *
     * @return Builder<TimeEntry>
     */
    private function filteredQuery(Request $request): Builder
    {
        /** @var User $user */
        $user = $request->user();
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        return TimeEntry::query()
            ->visibleTo($user)
            ->with('employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path', 'approver:id,name')
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->string('project')->toString(), fn ($q, $project) => $q->where('project', $project))
            ->when($request->date('date_from'), fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($request->date('date_to'), fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('description', 'like', "%{$search}%")
                ->orWhere('project', 'like', "%{$search}%")
                ->orWhereHas('employee.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))));
    }

    /**
     * @param  Builder<TimeEntry>  $query
     * @return Builder<TimeEntry>
     */
    private function applyStatus(Builder $query, Request $request): Builder
    {
        return $query->when(in_array($request->input('status'), TimeEntry::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?TimeEntry $timeEntry = null): array
    {
        /** @var User $user */
        $user = $request->user();

        return $this->validateInput($user, [...$request->all(), 'is_billable' => $request->boolean('is_billable')], $timeEntry);
    }

    /**
     * Validate an entry (form or CSV row), resolve the employee and enforce the daily hours cap.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validateInput(User $user, array $input, ?TimeEntry $timeEntry = null): array
    {
        $canActForOthers = $user->can('manage-any-time-entries');

        $data = Validator::make($input, [
            'employee_id' => [Rule::requiredIf($canActForOthers), 'integer', Rule::exists('employees', 'id')],
            'date' => ['required', 'date_format:Y-m-d'],
            'project' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'hours' => ['required', 'numeric', 'gt:0', 'max:'.TimeEntry::MAX_DAY_HOURS],
            'start_time' => ['nullable', 'date_format:H:i', 'required_with:end_time'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'is_billable' => ['boolean'],
        ])->validate();

        // Employees log their own time: the employee comes from the signed-in user.
        if (! $canActForOthers) {
            $data['employee_id'] = $timeEntry->employee_id ?? $user->employee()->value('id')
                ?? throw ValidationException::withMessages(['employee_id' => __('Your account has no employee profile.')]);
        }

        $logged = (float) TimeEntry::query()
            ->where('employee_id', $data['employee_id'])
            ->whereDate('date', $data['date'])
            ->when($timeEntry, fn ($q) => $q->whereKeyNot($timeEntry->id))
            ->sum('hours');

        if ($logged + (float) $data['hours'] > TimeEntry::MAX_DAY_HOURS) {
            throw ValidationException::withMessages(['hours' => __('This would log more than :max hours on that day (:logged already logged).', ['max' => TimeEntry::MAX_DAY_HOURS, 'logged' => $logged])]);
        }

        return $data;
    }
}
