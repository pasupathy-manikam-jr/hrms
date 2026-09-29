<?php

namespace Tests\Feature\Attendance;

use App\Models\Employee;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Support\SessionKey;
use Tests\TestCase;

class TimeEntryImportExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function entry(array $attributes = []): TimeEntry
    {
        return TimeEntry::create([
            'employee_id' => Employee::factory()->create()->id,
            'date' => '2026-09-21',
            'project' => 'Testing',
            'description' => 'Wrote tests',
            'hours' => 4,
            'status' => 'pending',
            ...$attributes,
        ]);
    }

    public function test_export_honours_the_filters_and_status_tab(): void
    {
        $this->entry(['description' => 'Kept row', 'project' => 'Bug Fixes']);
        $this->entry(['description' => 'Other project', 'project' => 'Testing']);
        $this->entry(['description' => 'Approved row', 'project' => 'Bug Fixes', 'status' => 'approved']);

        $csv = $this->actingAs($this->userWithRole())
            ->get(route('hr.time-entries.export', ['project' => 'Bug Fixes', 'status' => 'pending']))
            ->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF".'"Employee ID"', $csv);
        $this->assertStringContainsString('Kept row', $csv);
        $this->assertStringNotContainsString('Other project', $csv);
        $this->assertStringNotContainsString('Approved row', $csv);
    }

    public function test_template_can_be_downloaded(): void
    {
        $this->actingAs($this->userWithRole())->get(route('hr.time-entries.download.template'))
            ->assertOk()->assertDownload('time-entries-import-template.csv');
    }

    public function test_import_creates_valid_rows_and_reports_bad_ones_by_row_number(): void
    {
        $employee = Employee::factory()->create();
        $this->entry(['employee_id' => $employee->id, 'date' => '2026-09-22', 'hours' => 20]);

        $rows = implode("\n", [
            'Employee ID,Date,Hours,Project,Description,Billable',
            "{$employee->employee_id},2026-09-21,7.5,Testing,Imported work,Yes",
            "{$employee->employee_id},2026-09-22,6,Testing,Too many hours,No",
            'NOPE,2026-09-21,2,Testing,Unknown employee,No',
        ]);

        $this->actingAs($this->userWithRole())
            ->post(route('hr.time-entries.import'), ['file' => UploadedFile::fake()->createWithContent('t.csv', $rows)])
            ->assertRedirect();

        $imported = TimeEntry::query()->where('description', 'Imported work')->firstOrFail();
        $this->assertSame($employee->id, $imported->employee_id);
        $this->assertTrue($imported->is_billable);
        $this->assertSame('pending', $imported->status);

        $report = session(SessionKey::FLASH_DATA)['import'];
        $this->assertSame(1, $report['imported']);
        $this->assertSame([3, 4], array_column($report['skipped'], 'row'));
        $this->assertStringContainsString('more than 24 hours', implode(' ', $report['skipped'][0]['errors']));
    }

    public function test_export_and_import_need_their_permissions(): void
    {
        $this->actingAs($this->userWithRole('hr'));

        $this->get(route('hr.time-entries.export'))->assertForbidden();
        $this->get(route('hr.time-entries.download.template'))->assertForbidden();
        $this->post(route('hr.time-entries.import'), ['file' => UploadedFile::fake()->createWithContent('t.csv', "Employee ID\n")])->assertForbidden();
    }
}
