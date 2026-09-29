<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Inertia\Support\SessionKey;
use Tests\TestCase;

class AttendanceRecordImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-16 12:00:00');
    }

    public function test_export_streams_the_shown_month_for_the_filtered_employee(): void
    {
        $company = $this->userWithRole();
        $a = AttendanceRecord::factory()->create(['date' => '2026-08-03', 'notes' => 'In August']);
        AttendanceRecord::factory()->create(['date' => '2026-08-04']);
        AttendanceRecord::factory()->create(['employee_id' => $a->employee_id, 'date' => '2026-09-01', 'notes' => 'In September']);

        $csv = $this->actingAs($company)
            ->get(route('hr.attendance-records.export', ['month' => 8, 'year' => 2026, 'employee' => $a->employee_id]))
            ->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF".'"Employee ID"', $csv);
        $this->assertStringContainsString('In August', $csv);
        $this->assertStringNotContainsString('In September', $csv);
        $this->assertSame(2, substr_count(trim($csv), "\n") + 1);
    }

    public function test_template_can_be_downloaded(): void
    {
        $this->actingAs($this->userWithRole())->get(route('hr.attendance-records.download.template'))
            ->assertOk()->assertDownload('attendance-records-import-template.csv');
    }

    public function test_import_computes_times_and_reports_bad_rows_by_row_number(): void
    {
        $company = $this->userWithRole();
        $employee = Employee::factory()->create(['shift_id' => Shift::factory()->create()->id]);
        AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'date' => '2026-09-02']);

        $rows = implode("\n", [
            'Employee ID,Date,Status,Clock In,Clock Out,Notes',
            "{$employee->employee_id},2026-09-01,Present,09:30,19:00,Traffic",
            "{$employee->employee_id},2026-09-02,present,09:00,18:00,",
            'NOPE,2026-09-03,present,09:00,18:00,',
        ]);

        $this->actingAs($company)
            ->post(route('hr.attendance-records.import'), ['file' => UploadedFile::fake()->createWithContent('a.csv', $rows)])
            ->assertRedirect();

        $record = AttendanceRecord::query()->where('employee_id', $employee->id)->whereDate('date', '2026-09-01')->firstOrFail();
        $this->assertTrue($record->is_late);
        $this->assertSame(8.5, $record->total_hours);
        $this->assertSame(0.5, $record->overtime_hours);

        $report = session(SessionKey::FLASH_DATA)['import'];
        $this->assertSame(1, $report['imported']);
        $this->assertSame([3, 4], array_column($report['skipped'], 'row'));
        $this->assertStringContainsString('already has a record', implode(' ', $report['skipped'][0]['errors']));
    }

    public function test_export_and_import_need_their_permissions(): void
    {
        $this->actingAs($this->userWithRole('hr'));

        $this->get(route('hr.attendance-records.export'))->assertForbidden();
        $this->get(route('hr.attendance-records.download.template'))->assertForbidden();
        $this->post(route('hr.attendance-records.import'), ['file' => UploadedFile::fake()->createWithContent('a.csv', "Employee ID\n")])->assertForbidden();
    }
}
