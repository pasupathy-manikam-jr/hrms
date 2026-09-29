<?php

namespace Tests\Feature\Hr;

use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Support\SessionKey;
use Tests\TestCase;

class EmployeeImportExportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('employees.csv', $contents);
    }

    public function test_export_streams_the_filtered_list_and_neutralises_formulas()
    {
        $company = $this->userWithRole();
        $a = Employee::factory()->create();
        $a->user->update(['name' => '=HYPERLINK("http://evil")']);
        $b = Employee::factory()->create();

        $csv = $this->actingAs($company)->get(route('hr.employees.export', ['branch' => $a->branch_id]))->assertOk()->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString($a->employee_id, $csv);
        $this->assertStringNotContainsString($b->employee_id, $csv);
        $this->assertStringStartsWith("\xEF\xBB\xBF".'"Employee ID"', $csv);
    }

    public function test_template_can_be_downloaded()
    {
        $this->actingAs($this->userWithRole())->get(route('hr.employees.download.template'))
            ->assertOk()->assertDownload('employees-import-template.csv');
    }

    public function test_import_creates_valid_rows_and_reports_bad_ones_by_row_number()
    {
        $company = $this->userWithRole();
        $designation = Designation::factory()->create(['name' => 'Accountant']);
        // Fixed names: random factory names can contain commas, which would split the CSV cells.
        $department = tap($designation->department)->update(['name' => 'Finance']);
        $branch = tap($department->branch)->update(['name' => 'Kuala Lumpur']);
        User::factory()->create(['email' => 'taken@example.com']);

        $rows = implode("\n", [
            'Employee ID,Name,Email,Password,Phone,Gender,Date of Birth,Branch,Department,Designation,Shift,Date of Joining,Employment Type,Status',
            ",Aminah binti Yusof,aminah@example.com,,012,Female,1990-01-01,{$branch->name},{$department->name},Accountant,,2026-01-05,Full-time,Active",
            ",Dup Email,taken@example.com,,,,,{$branch->name},{$department->name},Accountant,,2026-01-05,Full-time,active",
            ',No Branch,nobranch@example.com,,,,,Atlantis,Nowhere,Accountant,,2026-01-05,Full-time,active',
            '',
        ]);

        $this->actingAs($company)->post(route('hr.employees.import'), ['file' => $this->csv($rows)])->assertRedirect();

        $aminah = User::where('email', 'aminah@example.com')->firstOrFail();
        $this->assertTrue($aminah->hasRole('employee'));
        $this->assertSame($company->id, $aminah->reports_to_id);
        $this->assertSame('female', $aminah->employee->gender);
        $this->assertSame($designation->id, $aminah->employee->designation_id);
        $this->assertNull(User::where('email', 'nobranch@example.com')->first());

        $report = session(SessionKey::FLASH_DATA)['import'];
        $this->assertSame(1, $report['imported']);
        $this->assertSame([3, 4], array_column($report['skipped'], 'row'));
        $this->assertStringContainsString('email', strtolower(implode(' ', $report['skipped'][0]['errors'])));
    }

    public function test_import_rejects_non_csv_files_and_needs_permission()
    {
        $this->actingAs($this->userWithRole())
            ->post(route('hr.employees.import'), ['file' => UploadedFile::fake()->create('x.php', 5, 'text/x-php')])
            ->assertSessionHasErrors('file');

        $this->actingAs($this->userWithRole('hr'));
        $this->get(route('hr.employees.export'))->assertForbidden();
        $this->post(route('hr.employees.import'), ['file' => $this->csv("Name\nX")])->assertForbidden();
    }
}
