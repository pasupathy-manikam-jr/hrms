<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\BiometricPunch;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BiometricAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-09-16 12:00:00');
    }

    private function mapped(string $biometricId): Employee
    {
        $employee = Employee::factory()->create(['shift_id' => Shift::factory()->create()->id]);
        $employee->forceFill(['biometric_emp_id' => $biometricId])->save();

        return $employee;
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('punches.csv', $content);
    }

    public function test_import_turns_first_and_last_punch_into_attendance_and_reports_skipped_rows(): void
    {
        $alice = $this->mapped('101');
        $bob = $this->mapped('102');
        // An existing record for Bob's day is updated, not duplicated.
        AttendanceRecord::factory()->for($bob)->create(['date' => '2026-09-15', 'status' => 'absent', 'clock_in' => null, 'clock_out' => null]);

        $csv = implode("\n", [
            'biometric_emp_id,timestamp',
            '101,2026-09-15 12:30:00',
            '101,2026-09-15 09:20:00',
            '101,2026-09-15 18:05',
            '102,2026-09-15 08:55:00',
            '999,2026-09-15 09:00:00',
            '101,15/09/2026 09:00',
            '101,2026-09-20 09:00:00',
            '101,2026-09-15 09:20:00',
            ',',
        ]);

        $this->actingAs($this->userWithRole('company'))
            ->post(route('hr.biometric-attendance.import'), ['file' => $this->csv($csv)])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(4, BiometricPunch::query()->count());

        $record = AttendanceRecord::query()->where('employee_id', $alice->id)->sole();
        $this->assertSame('2026-09-15', $record->date->toDateString());
        $this->assertSame('09:20', substr((string) $record->clock_in, 0, 5));
        $this->assertSame('18:05', substr((string) $record->clock_out, 0, 5));
        $this->assertTrue($record->is_late);
        $this->assertSame('present', $record->status);
        $this->assertSame(7.75, $record->total_hours);

        // A single punch is a clock in with no clock out yet.
        $bobs = AttendanceRecord::query()->where('employee_id', $bob->id)->sole();
        $this->assertSame('present', $bobs->status);
        $this->assertSame('08:55', substr((string) $bobs->clock_in, 0, 5));
        $this->assertNull($bobs->clock_out);

        $flash = session('inertia.flash_data', []);
        $result = $flash['biometricImport'] ?? null;
        $this->assertNotNull($result, 'import result is flashed');
        $this->assertSame(4, $result['imported']);
        $this->assertSame(2, $result['days']);
        $this->assertSame(5, $result['skipped_total']);
        $this->assertSame([6, 7, 8, 9, 10], array_column($result['skipped'], 'line'));
        $this->assertSame(['line' => 6, 'reason' => 'Unknown biometric ID', 'value' => '999,2026-09-15 09:00:00'], $result['skipped'][0]);
        $this->assertStringContainsString('Invalid timestamp', $result['skipped'][1]['reason']);
        $this->assertStringContainsString('future', $result['skipped'][2]['reason']);
        $this->assertStringContainsString('Duplicate', $result['skipped'][3]['reason']);
        $this->assertStringContainsString('Missing', $result['skipped'][4]['reason']);

        // Re-importing the same file changes nothing.
        $this->post(route('hr.biometric-attendance.import'), ['file' => $this->csv($csv)])->assertSessionHasNoErrors();
        $this->assertSame(4, BiometricPunch::query()->count());
        $this->assertSame(2, AttendanceRecord::query()->count());

        $this->get(route('hr.biometric-attendance.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/biometric-attendance/index')
                ->has('punchDays.data', 2)
                ->where('punchDays.data.0.employee_id', $alice->id)
                ->where('punchDays.data.0.employee_code', '101')
                ->where('punchDays.data.0.total_entries', 3)
                ->where('punchDays.data.0.punches', ['09:20:00', '12:30:00', '18:05:00'])
                ->where('punchDays.data.1.clock_out', null)
                ->has('employees', 2));
    }

    public function test_import_rejects_non_csv_files(): void
    {
        $this->actingAs($this->userWithRole('company'))
            ->post(route('hr.biometric-attendance.import'), ['file' => UploadedFile::fake()->create('photo.png', 10, 'image/png')])
            ->assertSessionHasErrors('file');

        $this->post(route('hr.biometric-attendance.import'))->assertSessionHasErrors('file');
    }

    public function test_biometric_ids_are_unique_and_clearable(): void
    {
        $this->mapped('101');
        $other = Employee::factory()->create();
        $this->actingAs($this->userWithRole('company'));

        $this->put(route('hr.biometric-attendance.update-mapping', $other), ['biometric_emp_id' => '101'])->assertSessionHasErrors('biometric_emp_id');
        $this->put(route('hr.biometric-attendance.update-mapping', $other), ['biometric_emp_id' => '205'])->assertSessionHasNoErrors();
        $this->assertSame('205', $other->fresh()?->getAttribute('biometric_emp_id'));

        $this->put(route('hr.biometric-attendance.update-mapping', $other), ['biometric_emp_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($other->fresh()->getAttribute('biometric_emp_id'));
    }

    public function test_only_the_company_manages_biometric_attendance(): void
    {
        $employee = Employee::factory()->create();

        foreach (['hr', 'employee'] as $role) {
            $this->actingAs($this->userWithRole($role));
            $this->get(route('hr.biometric-attendance.index'))->assertForbidden();
            $this->post(route('hr.biometric-attendance.import'), ['file' => $this->csv("101,2026-09-15 09:00\n")])->assertForbidden();
            $this->put(route('hr.biometric-attendance.update-mapping', $employee), ['biometric_emp_id' => '1'])->assertForbidden();
        }
    }

    public function test_list_shows_one_day_defaulting_to_the_latest_day_with_punches(): void
    {
        $alice = $this->mapped('101');
        BiometricPunch::import([
            ['employee_id' => $alice->id, 'date' => '2026-09-14', 'time' => '09:00:00'],
            ['employee_id' => $alice->id, 'date' => '2026-09-15', 'time' => '09:10:00'],
            ['employee_id' => $alice->id, 'date' => '2026-09-15', 'time' => '18:00:00'],
        ]);
        $this->actingAs($this->userWithRole('company'));

        $this->get(route('hr.biometric-attendance.index'))->assertInertia(fn ($page) => $page
            ->where('date', '2026-09-15')
            ->has('punchDays.data', 1)
            ->where('punchDays.data.0.punches', ['09:10:00', '18:00:00']));

        $this->get(route('hr.biometric-attendance.index', ['date' => '2026-09-14']))->assertInertia(fn ($page) => $page
            ->where('date', '2026-09-14')
            ->where('punchDays.data.0.total_entries', 1));

        $this->get(route('hr.biometric-attendance.index', ['date' => 'nope']))->assertSessionHasErrors('date');
    }

    public function test_view_page_shows_the_days_punches_and_attendance(): void
    {
        $alice = $this->mapped('101');
        BiometricPunch::import([
            ['employee_id' => $alice->id, 'date' => '2026-09-15', 'time' => '18:00:00'],
            ['employee_id' => $alice->id, 'date' => '2026-09-15', 'time' => '09:10:00'],
        ]);
        $this->actingAs($this->userWithRole('company'));

        $this->get(route('hr.biometric-attendance.show', [$alice->id, '2026-09-15']))->assertInertia(fn ($page) => $page
            ->component('hr/biometric-attendance/show')
            ->where('employee.employee_code', '101')
            ->where('punches', ['09:10:00', '18:00:00'])
            ->where('attendance.clock_in', fn ($value) => str_starts_with((string) $value, '09:10')));

        $this->get(route('hr.biometric-attendance.show', [$alice->id, '2026-09-16']))->assertNotFound();
        $this->get(route('hr.biometric-attendance.show', [$alice->id, 'nope']))->assertNotFound();
    }
}
