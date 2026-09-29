<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Support\SessionKey;
use Tests\TestCase;

class PayrollRunImportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_streams_the_filtered_list()
    {
        PayrollRun::factory()->create(['title' => 'Kept Run', 'status' => 'draft']);
        PayrollRun::factory()->create(['title' => 'Other Run', 'status' => 'completed']);

        $csv = $this->actingAs($this->userWithRole())->get(route('hr.payroll-runs.export', ['status' => 'draft']))->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF".'Title,', $csv);
        $this->assertStringContainsString('Kept Run', $csv);
        $this->assertStringNotContainsString('Other Run', $csv);
    }

    public function test_template_can_be_downloaded()
    {
        $this->actingAs($this->userWithRole())->get(route('hr.payroll-runs.download.template'))
            ->assertOk()->assertDownload('payroll-runs-import-template.csv');
    }

    public function test_import_creates_draft_runs_and_reports_bad_rows()
    {
        $rows = implode("\n", [
            'Title,Payroll Frequency,Pay Period Start,Pay Period End,Pay Date,Notes',
            'March 2026 Payroll,Monthly,2026-03-01,2026-03-31,2026-04-05,Imported',
            'Backwards,monthly,2026-03-31,2026-03-01,2026-04-05,',
            ',daily,2026-03-01,2026-03-31,2026-04-05,',
        ]);

        $this->actingAs($this->userWithRole())
            ->post(route('hr.payroll-runs.import'), ['file' => UploadedFile::fake()->createWithContent('runs.csv', $rows)])
            ->assertRedirect();

        $run = PayrollRun::query()->sole();
        $this->assertSame(['March 2026 Payroll', 'monthly', 'draft', 0], [$run->title, $run->payroll_frequency, $run->fresh()->status, $run->payslips()->count()]);

        $report = session(SessionKey::FLASH_DATA)['import'];
        $this->assertSame(1, $report['imported']);
        $this->assertSame([3, 4], array_column($report['skipped'], 'row'));
        $this->assertStringContainsString('pay period end', strtolower(implode(' ', $report['skipped'][0]['errors'])));
    }

    public function test_import_and_export_need_permission()
    {
        $this->actingAs($this->userWithRole('hr'));
        $this->get(route('hr.payroll-runs.export'))->assertForbidden();
        $this->post(route('hr.payroll-runs.import'), ['file' => UploadedFile::fake()->createWithContent('runs.csv', "Title\nX")])->assertForbidden();
    }
}
