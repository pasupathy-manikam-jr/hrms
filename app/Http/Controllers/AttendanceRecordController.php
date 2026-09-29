<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Support\Csv;
use App\Support\TableQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceRecordController extends Controller
{
    /**
     * The demo's monthly grid: one row per employee, one cell per day of the month.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $today = AttendanceRecord::now();
        $start = $this->month($request);
        [$month, $year] = [$start->month, $start->year];
        $workingDays = array_map('intval', (array) Setting::get('workingDays'));

        $days = collect(range(1, $start->daysInMonth))->map(function (int $day) use ($start, $today, $workingDays) {
            $date = $start->setDay($day);

            return [
                'day' => $day,
                'date' => $date->toDateString(),
                'day_name' => $date->format('D'),
                'is_weekend' => ! in_array($date->dayOfWeek, $workingDays, true),
                'is_future' => $date->toDateString() > $today->toDateString(),
            ];
        });
        $workingCount = $days->where('is_weekend', false)->count();

        $employees = $this->employees($user)
            ->with('user:id,name,avatar_path', 'designation:id,name', 'shift:id,name')
            ->when($request->integer('employee'), fn ($q, $id) => $q->whereKey($id));
        $perPage = in_array($request->integer('per_page'), TableQuery::PER_PAGE, true) ? $request->integer('per_page') : TableQuery::PER_PAGE[0];
        $page = $employees->orderBy('id')->paginate($perPage)->withQueryString();

        $records = AttendanceRecord::query()
            ->whereIn('employee_id', $page->getCollection()->modelKeys())
            ->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->keyBy(fn (AttendanceRecord $r) => $r->date->toDateString()));

        $rows = $page->through(function (Employee $employee) use ($days, $records, $workingCount) {
            $mine = $records->get($employee->id, collect());
            $cells = $days->map(fn (array $day) => ($record = $mine->get($day['date']))
                ? [...$this->present($record), 'is_weekend' => $day['is_weekend']]
                : ['date' => $day['date'], 'status' => match (true) {
                    $day['is_weekend'] => 'day_off',
                    $day['is_future'] => 'future',
                    default => 'absent',
                }, 'is_weekend' => $day['is_weekend']]);

            return [
                'id' => $employee->id,
                'name' => $employee->user->name,
                'avatar' => $employee->user->avatar,
                'gender' => $employee->gender,
                'employee_id' => $employee->employee_id,
                'designation' => $employee->designation?->name,
                'shift' => $employee->shift?->name,
                'days' => $cells->values(),
                'present_days' => $mine->where('status', 'present')->count()
                    + $mine->where('status', 'on_leave')->count()
                    + $mine->where('status', 'half_day')->count() / 2,
                'total_working_days' => $workingCount,
            ];
        });

        return Inertia::render('hr/attendance-records/index', [
            'employeeRows' => $rows,
            'dayHeaders' => $days->map(fn ($d) => collect($d)->except('date'))->values(),
            'employees' => $this->employees($user)->with('user:id,name')->orderBy('id')->get()
                ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->user->name, 'employee_id' => $e->employee_id]),
            'monthOptions' => collect(range(1, 12))->map(fn ($m) => ['value' => (string) $m, 'label' => CarbonImmutable::create(2000, $m, 1)->format('F')]),
            'yearOptions' => collect(range($today->year - 2, $today->year + 1))->map(fn ($y) => ['value' => (string) $y, 'label' => (string) $y]),
            'statuses' => AttendanceRecord::STATUSES,
            'currentMonth' => $month,
            'currentYear' => $year,
            'daysInMonth' => $start->daysInMonth,
            'filters' => $request->only(['month', 'year', 'employee', 'per_page']),
        ]);
    }

    /** CSV columns shared by the template and the import (the export adds computed columns). */
    private const CSV_COLUMNS = ['Employee ID' => 'employee_id', 'Date' => 'date', 'Status' => 'status', 'Clock In' => 'clock_in', 'Clock Out' => 'clock_out', 'Notes' => 'notes'];

    /**
     * The shown month's records for the visible (and filtered) employees.
     */
    public function export(Request $request): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $start = $this->month($request);

        $records = AttendanceRecord::query()
            ->with('employee:id,user_id,employee_id,shift_id', 'employee.user:id,name', 'employee.shift:id,name')
            ->whereIn('employee_id', $this->employees($user)->when($request->integer('employee'), fn ($q, $id) => $q->whereKey($id))->select('id'))
            ->whereBetween('date', [$start->toDateString(), $start->endOfMonth()->toDateString()])
            ->orderBy('date')->orderBy('employee_id')
            ->lazy();

        return Csv::download('attendance-records-'.$start->format('Y-m').'.csv', [
            'Employee ID', 'Employee', 'Date', 'Shift', 'Clock In', 'Clock Out', 'Total Hours', 'Overtime Hours', 'Status', 'Is Late', 'Is Early Departure', 'Notes',
        ], $records->map(fn (AttendanceRecord $r) => [
            $r->employee->employee_id, $r->employee->user->name, $r->date->toDateString(), $r->employee->shift?->name,
            $r->clock_in ? substr($r->clock_in, 0, 5) : null, $r->clock_out ? substr($r->clock_out, 0, 5) : null,
            $r->total_hours, $r->overtime_hours, $r->status, $r->is_late ? 'Yes' : 'No', $r->is_early_departure ? 'Yes' : 'No', $r->notes,
        ]));
    }

    public function template(Request $request): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $sample = $this->employees($user)->orderBy('id')->value('employee_id');

        return Csv::download('attendance-records-import-template.csv', array_keys(self::CSV_COLUMNS), [
            [$sample, AttendanceRecord::now()->toDateString(), 'present', '09:00', '18:00', ''],
        ]);
    }

    /**
     * Import days from the template's CSV. Rows go through the form's rules and the same
     * late / hours / overtime calculation; bad rows are skipped and reported by row number.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => Csv::uploadRules()]);

        /** @var User $user */
        $user = $request->user();
        $employees = $this->employees($user)->with('shift')->get()->keyBy(fn (Employee $e) => mb_strtolower($e->employee_id));
        $imported = 0;
        $skipped = [];

        foreach (Csv::read($request->file('file')) as $line => $row) {
            $input = collect(self::CSV_COLUMNS)->mapWithKeys(fn ($field, $header) => [$field => ($row[Str::snake($header)] ?? '') === '' ? null : $row[Str::snake($header)]])->all();
            $employee = $employees[mb_strtolower((string) $input['employee_id'])] ?? null;
            $input['employee_id'] = $employee?->id;
            $input['status'] = mb_strtolower(str_replace(' ', '_', (string) $input['status'])) ?: 'present';

            $validator = Validator::make($input, $this->rules($user, $input), $this->messages());

            if ($validator->fails()) {
                $skipped[] = ['row' => $line, 'errors' => $validator->errors()->all()];

                continue;
            }

            (new AttendanceRecord($validator->validated()))->computeTimes($employee?->shift)->save();
            $imported++;
        }

        Inertia::flash('import', ['imported' => $imported, 'skipped' => $skipped]);

        return $skipped === []
            ? $this->done(__(':count attendance records imported.', ['count' => $imported]))
            : $this->toast('error', __(':imported imported, :skipped skipped. See the import report.', ['imported' => $imported, 'skipped' => count($skipped)]));
    }

    public function store(Request $request): RedirectResponse
    {
        $record = new AttendanceRecord($this->validated($request));
        $record->computeTimes(Employee::query()->find($record->employee_id)?->shift)->save();

        return $this->done(__('Attendance record created successfully.'));
    }

    public function update(Request $request, AttendanceRecord $attendanceRecord): RedirectResponse
    {
        $this->authorizeRecord($request, $attendanceRecord);
        $attendanceRecord->fill($this->validated($request, $attendanceRecord));
        $attendanceRecord->computeTimes(Employee::query()->find($attendanceRecord->employee_id)?->shift)->save();

        return $this->done(__('Attendance record updated successfully.'));
    }

    public function destroy(Request $request, AttendanceRecord $attendanceRecord): RedirectResponse
    {
        $this->authorizeRecord($request, $attendanceRecord);
        $attendanceRecord->delete();

        return $this->done(__('Attendance record deleted successfully.'));
    }

    /**
     * Everyone for manage-any-attendance-records; only the user's own employee row for manage-own.
     *
     * @return Builder<Employee>
     */
    private function employees(User $user): Builder
    {
        return Employee::query()->when(
            ! $user->can('manage-any-attendance-records'),
            fn ($q) => $q->where('user_id', $user->can('manage-own-attendance-records') ? $user->id : 0),
        );
    }

    private function authorizeRecord(Request $request, AttendanceRecord $record): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->employees($user)->whereKey($record->employee_id)->exists(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AttendanceRecord $record): array
    {
        return [
            ...$record->only(['id', 'employee_id', 'status', 'total_hours', 'is_late', 'is_early_departure', 'overtime_hours', 'notes']),
            'date' => $record->date->toDateString(),
            'clock_in' => $record->clock_in ? substr($record->clock_in, 0, 5) : null,
            'clock_out' => $record->clock_out ? substr($record->clock_out, 0, 5) : null,
        ];
    }

    /**
     * The month shown (and exported): ?month=&year=, defaulting to the current one.
     */
    private function month(Request $request): CarbonImmutable
    {
        $today = AttendanceRecord::now();

        return CarbonImmutable::create(
            min($today->year + 1, max($today->year - 2, $request->integer('year', $today->year))),
            min(12, max(1, $request->integer('month', $today->month))),
            1,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AttendanceRecord $record = null): array
    {
        /** @var User $user */
        $user = $request->user();

        return $request->validate($this->rules($user, $request->all(), $record), $this->messages());
    }

    /**
     * Record rules, shared by the form and the CSV import.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function rules(User $user, array $input, ?AttendanceRecord $record = null): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::in($this->employees($user)->pluck('id'))],
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('attendance_records')->where('employee_id', (int) ($input['employee_id'] ?? 0))->ignore($record?->id)],
            'status' => ['required', Rule::in(AttendanceRecord::STATUSES)],
            'clock_in' => ['nullable', 'date_format:H:i', 'required_with:clock_out'],
            'clock_out' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return ['date.unique' => __('This employee already has a record for that date.')];
    }
}
