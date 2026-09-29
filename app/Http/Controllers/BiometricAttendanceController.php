<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\BiometricPunch;
use App\Models\Employee;
use App\Support\TableQuery;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use SplFileObject;

/**
 * Biometric attendance without a live device: map employees to their device IDs and import the
 * device's punch export (CSV). Each employee's first and last punch of a day become that day's
 * attendance record.
 */
class BiometricAttendanceController extends Controller
{
    /** How many skipped rows are listed back to the user. */
    private const SKIPPED_SHOWN = 100;

    /**
     * One day's punches (the demo's day view): the chosen day, else the latest day with punches.
     */
    public function index(Request $request): Response
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $perPage = in_array($request->integer('per_page'), TableQuery::PER_PAGE, true) ? $request->integer('per_page') : TableQuery::PER_PAGE[0];
        $date = $request->date('date')?->toDateString()
            ?? BiometricPunch::query()->where('date', '<=', today()->toDateString())->max('date')
            ?? today()->toDateString();

        $people = Employee::query()->with('user:id,name,avatar_path', 'department:id,name', 'designation:id,name')->orderBy('employee_id')->get();
        $search = trim($request->string('search')->toString());

        // One row per employee: first / last punch of the day and every punch between.
        $days = BiometricPunch::query()
            ->selectRaw('min(id) as id, employee_id, date, min(time) as clock_in, max(time) as clock_out, count(*) as total_entries, group_concat(time) as punch_times')
            ->where('date', $date)
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($search !== '', fn ($q) => $q->whereIn('employee_id', $people
                ->filter(fn (Employee $e) => str_contains(mb_strtolower($e->user->name.' '.$e->getAttribute('biometric_emp_id')), mb_strtolower($search)))
                ->modelKeys()))
            ->groupBy('employee_id', 'date')
            ->orderBy('employee_id')
            ->paginate($perPage)
            ->withQueryString();

        $byId = $people->keyBy('id');

        return Inertia::render('hr/biometric-attendance/index', [
            'date' => $date,
            'punchDays' => $days->through(fn (BiometricPunch $day) => $this->dayRow($day, $byId->get($day->employee_id))),
            'employees' => $people->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->user->name,
                'employee_id' => $e->employee_id,
                'biometric_emp_id' => $e->getAttribute('biometric_emp_id'),
                'avatar' => $e->user->avatar,
                'gender' => $e->gender,
                'department' => $e->department?->name,
                'designation' => $e->designation?->name,
            ]),
            'filters' => TableQuery::filters($request, ['employee_id', 'date']),
        ]);
    }

    /**
     * One employee's punches on one day, and the attendance record built from them.
     */
    public function show(Employee $employee, string $date): Response
    {
        abort_unless(CarbonImmutable::hasFormat($date, 'Y-m-d'), 404);

        $punches = BiometricPunch::query()->where('employee_id', $employee->id)->where('date', $date)->orderBy('time')->pluck('time');
        abort_if($punches->isEmpty(), 404);

        $employee->load('user:id,name,email,avatar_path', 'department:id,name', 'designation:id,name', 'shift:id,name,start_time,end_time');

        return Inertia::render('hr/biometric-attendance/show', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->user->name,
                'email' => $employee->user->email,
                'avatar' => $employee->user->avatar,
                'gender' => $employee->gender,
                'employee_id' => $employee->employee_id,
                'employee_code' => $employee->getAttribute('biometric_emp_id'),
                'department' => $employee->department?->name,
                'designation' => $employee->designation?->name,
                'shift' => $employee->shift?->only(['name', 'start_time', 'end_time']),
            ],
            'date' => $date,
            'punches' => $punches,
            'attendance' => AttendanceRecord::query()->where('employee_id', $employee->id)->whereDate('date', $date)
                ->first(['status', 'clock_in', 'clock_out', 'total_hours', 'is_late', 'is_early_departure', 'overtime_hours']),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function dayRow(BiometricPunch $day, ?Employee $employee): array
    {
        $times = explode(',', (string) $day->getAttribute('punch_times'));
        sort($times);

        return [
            'id' => $day->id,
            'employee_id' => $day->employee_id,
            'name' => $employee?->user->name,
            'avatar' => $employee?->user->avatar,
            'gender' => $employee?->gender,
            'designation' => $employee?->designation?->name,
            'employee_code' => $employee?->getAttribute('biometric_emp_id'),
            'date' => $day->date,
            'clock_in' => $day->getAttribute('clock_in'),
            'clock_out' => (int) $day->getAttribute('total_entries') > 1 ? $day->getAttribute('clock_out') : null,
            'total_entries' => (int) $day->getAttribute('total_entries'),
            'punches' => $times,
        ];
    }

    public function updateMapping(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'biometric_emp_id' => ['nullable', 'string', 'max:50', Rule::unique('employees', 'biometric_emp_id')->ignore($employee->id)],
        ]);

        $employee->forceFill(['biometric_emp_id' => $data['biometric_emp_id'] ?? null])->save();

        return $this->done(__('Biometric ID updated.'));
    }

    /**
     * Import a CSV of punches (biometric_emp_id, timestamp) and rebuild the touched days' attendance.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'extensions:csv,txt', 'max:2048']]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        ['punches' => $punches, 'skipped' => $skipped] = $this->parse($file);

        ['imported' => $imported, 'days' => $days] = BiometricPunch::import($punches);

        Inertia::flash('biometricImport', [
            'imported' => $imported,
            'days' => $days,
            'skipped_total' => count($skipped),
            'skipped' => array_slice($skipped, 0, self::SKIPPED_SHOWN),
        ]);

        return $this->done(__(':imported punches imported for :days attendance days; :skipped rows skipped.', [
            'imported' => $imported, 'days' => $days, 'skipped' => count($skipped),
        ]));
    }

    /**
     * Validate each CSV row; valid ones become punches, the rest are reported with a reason.
     *
     * @return array{punches: list<array{employee_id: int, date: string, time: string}>, skipped: list<array{line: int, reason: string, value: string}>}
     */
    private function parse(UploadedFile $file): array
    {
        $ids = Employee::query()->whereNotNull('biometric_emp_id')->pluck('id', 'biometric_emp_id');
        $now = AttendanceRecord::now()->format('Y-m-d H:i:s');
        $punches = [];
        $skipped = [];
        $seen = [];

        $csv = new SplFileObject($file->getRealPath());
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);

        foreach ($csv as $index => $row) {
            $line = (int) $index + 1;
            $bioId = trim((string) ($row[0] ?? ''));
            $stamp = trim((string) ($row[1] ?? ''));

            if ($line === 1 && strtolower($bioId) === 'biometric_emp_id') {
                continue; // header
            }

            $at = $this->parseTimestamp($stamp);
            // Reasons are English keys; the page translates them.
            $reason = match (true) {
                $bioId === '' || $stamp === '' => 'Missing biometric ID or timestamp',
                ! isset($ids[$bioId]) => 'Unknown biometric ID',
                $at === null => 'Invalid timestamp (use YYYY-MM-DD HH:MM:SS)',
                $at->format('Y-m-d H:i:s') > $now => 'Timestamp is in the future',
                isset($seen[$bioId.$at->format('Y-m-d H:i:s')]) => 'Duplicate of an earlier row',
                default => null,
            };

            if ($reason !== null) {
                $skipped[] = ['line' => $line, 'reason' => $reason, 'value' => implode(',', array_map(strval(...), (array) $row))];

                continue;
            }

            $seen[$bioId.$at->format('Y-m-d H:i:s')] = true;
            $punches[] = ['employee_id' => (int) $ids[$bioId], 'date' => $at->toDateString(), 'time' => $at->format('H:i:s')];
        }

        return ['punches' => $punches, 'skipped' => $skipped];
    }

    private function parseTimestamp(string $value): ?CarbonImmutable
    {
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $at = DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($at !== false && $at->format($format) === $value) {
                return CarbonImmutable::instance($at);
            }
        }

        return null;
    }
}
