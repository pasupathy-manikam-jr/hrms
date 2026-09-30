<?php

namespace Tests\Feature\Benchmark;

use App\Models\Benchmark\SurveyCycle;
use App\Models\Benchmark\SurveyParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class BenchmarkSurveyTest extends TestCase
{
    use RefreshDatabase;

    private const LISTS = [
        'IndustryList' => ['Manufacturing', 'Technology / IT'],
        'StateList' => ['Selangor', 'Kuala Lumpur'],
        'EmployeeBandList' => ['50-99', '200-499'],
        'RevenueBandList' => ['RM20 million - RM50 million'],
        'YesNo' => ['Yes', 'No'],
        'YesNoUnsure' => ['Yes', 'No', 'Not sure'],
        'YesNoNA' => ['Yes', 'No', 'Not applicable'],
        'JobLevelList' => ['Entry / Non-Executive', 'Executive'],
        'JobFamilyList' => ['Engineering & Technical', 'Human Resources'],
        'OwnershipTypeList' => ['Local / Malaysian-owned'],
        'ListedStatusList' => ['Private', 'Listed'],
        'HeadcountPlanList' => ['Growing', 'Stable', 'Reducing'],
        'RetrenchmentDriverList' => ['Cost reduction'],
        'ExperienceRequiredList' => ['<2 yrs', '2-4 yrs'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_cycle_is_created_from_the_blank_template()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('benchmark.cycles.store'), ['name' => '', 'status' => 'x', 'min_companies' => 0])
            ->assertSessionHasErrors(['name', 'status', 'min_companies', 'template']);
        $this->post(route('benchmark.cycles.store'), ['name' => '2025/2026', 'status' => 'open', 'min_companies' => 3, 'template' => UploadedFile::fake()->create('notes.xlsx', 5)])
            ->assertSessionHasErrors('template');

        $this->post(route('benchmark.cycles.store'), ['name' => '2025/2026', 'status' => 'open', 'min_companies' => 3, 'template' => $this->workbook(template: true)])
            ->assertSessionHasNoErrors();

        $cycle = SurveyCycle::firstOrFail();
        $this->assertSame(2, $cycle->jobs()->count());
        $this->assertSame(['Entry / Non-Executive', 'Executive'], $cycle->lookup('JobLevelList'));
        Storage::disk('local')->assertExists($cycle->template_path);

        $this->get(route('benchmark.cycles.index'))->assertInertia(fn ($page) => $page
            ->component('benchmark/cycles/index')
            ->where('cycles.data.0.jobs_count', 2));
    }

    public function test_completed_workbooks_can_be_uploaded_with_a_new_cycle()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('benchmark.cycles.store'), [
            'name' => '2025/2026', 'status' => 'open', 'min_companies' => 3, 'template' => $this->workbook(template: true),
            'files' => [$this->workbook('Alpha', file: 'alpha.xlsx'), $this->workbook('Beta', file: 'beta.xlsx', consent: 'No')],
        ])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('benchmarkUpload.0.status', 'imported')
            ->assertInertiaFlash('benchmarkUpload.1.status', 'rejected');

        $this->assertSame(['alpha'], SurveyCycle::firstOrFail()->participants()->pluck('company_key')->all());
    }

    public function test_workbooks_are_imported_rejected_skipped_and_replaced()
    {
        $cycle = $this->cycle();
        $this->actingAs($this->userWithRole());

        $bad = $this->workbook('Faulty Bhd', salary: [['title' => 'Astronaut', 'male' => 3, 'female' => 1, 'headcount' => 5]], consent: 'No');
        $this->post(route('benchmark.participants.upload'), ['cycle_id' => $cycle->id, 'files' => [$this->workbook('Alpha Sdn Bhd'), $bad]])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('benchmarkUpload.0.status', 'imported')
            ->assertInertiaFlash('benchmarkUpload.1.status', 'rejected');

        $participant = SurveyParticipant::firstOrFail();
        $this->assertSame('alpha', $participant->company_key);
        $this->assertSame(2, $participant->salaryRows()->count());
        $this->assertSame('Human Resources', $participant->salaryRows()->where('job_title', 'HR Executive')->value('job_family'));
        // Auto columns are recalculated: (3000×2 + 3300×2) / 4.
        $this->assertEquals(3150, $participant->salaryRows()->where('job_title', 'HR Executive')->value('avg_overall'));
        $this->assertSame('Yes', $participant->benefits()->where('item', 'outpatient_coverage')->value('company_value'));
        $this->assertNull($participant->benefits()->where('item', 'epf_employer_rate')->value('remarks'), 'template guidance text is dropped');
        $this->assertEquals(12.5, $participant->attrition->attrition_rate);
        Storage::disk('local')->assertExists($participant->file_path);

        $this->post(route('benchmark.participants.upload'), ['cycle_id' => $cycle->id, 'files' => [$this->workbook('ALPHA sdn. bhd.')]])
            ->assertInertiaFlash('benchmarkUpload.0.status', 'skipped');

        $this->post(route('benchmark.participants.upload'), ['cycle_id' => $cycle->id, 'files' => [$this->workbook('Alpha Sdn Bhd', median: 5000)], 'replace' => true])
            ->assertInertiaFlash('benchmarkUpload.0.status', 'replaced');

        $this->assertSame(1, SurveyParticipant::count());
        Storage::disk('local')->assertMissing($participant->file_path);
        $this->assertEquals(5000, SurveyParticipant::firstOrFail()->salaryRows()->where('job_title', 'Process Engineer')->value('median_salary'));
    }

    public function test_analytics_hide_figures_below_the_threshold_and_pool_the_rest()
    {
        $cycle = $this->cycle();
        $this->actingAs($this->userWithRole());
        $this->upload($cycle, ['Alpha' => 4000, 'Beta' => 5000]);

        $this->get(route('benchmark.analytics.index'))->assertInertia(fn ($page) => $page
            ->component('benchmark/analytics/index')
            ->where('report.overview.companies', 2)
            ->where('report.salaries.0.job_title', 'Process Engineer')
            ->where('report.salaries.0.suppressed', true)
            ->missing('report.salaries.0.median')
            ->where('report.attrition.suppressed', true));

        $this->upload($cycle, ['Gamma' => 6000]);

        $this->get(route('benchmark.analytics.index'))->assertInertia(fn ($page) => $page
            ->where('report.salaries.0.suppressed', false)
            ->where('report.salaries.0.p25', 4500)
            ->where('report.salaries.0.median', 5000)
            ->where('report.salaries.0.p75', 5500)
            ->where('report.allowances.types.0.prevalence', 100)
            ->where('report.benefits.5.yes_percent', 100)
            ->where('report.attrition.hardest_to_hire.0.name', 'Engineering & Technical')
            ->where('report.attrition.hardest_to_hire.0.score', 9));

        // Side-by-side: the comparison overrides the main filters; "*" means the whole market.
        $this->get(route('benchmark.analytics.index', ['industry' => 'Technology / IT', 'vs_industry' => '*']))
            ->assertInertia(fn ($page) => $page
                ->where('report.overview.companies', 0)
                ->where('comparison.overview.companies', 3)
                ->where('comparison.salaries.Process Engineer|Executive.median', 5000));
        $this->get(route('benchmark.analytics.index'))->assertInertia(fn ($page) => $page->where('comparison', null));

        // A cut with fewer companies than the threshold shows nothing.
        $this->get(route('benchmark.analytics.index', ['industry' => 'Technology / IT']))
            ->assertInertia(fn ($page) => $page->where('report.overview.companies', 0));

        $participant = SurveyParticipant::where('company_key', 'alpha')->firstOrFail();
        $this->get(route('benchmark.participants.show', $participant))->assertInertia(fn ($page) => $page
            ->component('benchmark/participants/show')
            ->where('salaryRows.0.market_median', 5000));

        $this->get(route('benchmark.analytics.export'))->assertOk()->assertDownload('benchmark-2025-2026.xlsx');
        $this->get(route('benchmark.analytics.pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_deleting_a_submission_removes_its_file()
    {
        $cycle = $this->cycle();
        $this->actingAs($this->userWithRole());
        $this->upload($cycle, ['Alpha' => 4000]);
        $participant = SurveyParticipant::firstOrFail();

        $this->get(route('benchmark.participants.download', $participant))->assertDownload('alpha.xlsx');
        $this->delete(route('benchmark.participants.destroy', $participant));

        $this->assertModelMissing($participant);
        Storage::disk('local')->assertMissing($participant->file_path);
    }

    public function test_only_the_company_role_can_use_the_benchmark_survey()
    {
        $cycle = $this->cycle();

        foreach (['hr', 'employee'] as $role) {
            $this->actingAs($this->userWithRole($role));
            $this->get(route('benchmark.analytics.index'))->assertForbidden();
            $this->post(route('benchmark.participants.upload'), ['cycle_id' => $cycle->id, 'files' => [$this->workbook('Alpha')]])->assertForbidden();
            $this->delete(route('benchmark.cycles.destroy', $cycle))->assertForbidden();
        }

        $this->assertSame(0, SurveyParticipant::count());
    }

    private function cycle(): SurveyCycle
    {
        $this->actingAs($this->userWithRole());
        $this->post(route('benchmark.cycles.store'), ['name' => '2025/2026', 'status' => 'open', 'min_companies' => 3, 'template' => $this->workbook(template: true)]);

        return SurveyCycle::firstOrFail();
    }

    /**
     * @param  array<string, int>  $companies  name => Process Engineer median
     */
    private function upload(SurveyCycle $cycle, array $companies): void
    {
        foreach ($companies as $name => $median) {
            $this->post(route('benchmark.participants.upload'), ['cycle_id' => $cycle->id, 'files' => [$this->workbook($name, median: $median, file: strtolower($name).'.xlsx')]])
                ->assertInertiaFlash('benchmarkUpload.0.status', 'imported');
        }
    }

    /**
     * A workbook laid out like the survey template: the blank template (Lookups filled) or a company's completed copy.
     *
     * @param  list<array<string, mixed>>|null  $salary
     */
    private function workbook(string $company = 'Alpha Sdn Bhd', bool $template = false, ?array $salary = null, string $consent = 'Yes', int $median = 4500, ?string $file = null): UploadedFile
    {
        $book = new Spreadsheet;
        $sheet = fn (string $name) => $book->addSheet(new Worksheet($book, $name));
        $book->removeSheetByIndex(0);

        $profile = $sheet('1. Company Profile');
        $salarySheet = $sheet('2. Salary Data');
        $benefits = $sheet('3. Benefits');
        $attrition = $sheet('4. Attrition & Hiring');
        $consentSheet = $sheet('5. Consent & Submission');
        $lookups = $sheet('Lookups');

        $salarySheet->setCellValue('C4', 'Standard Job Title');
        $salarySheet->fromArray(['Ex.', '=formula', 'HR Executive', 'Example', 'Executive', 99], null, 'A5');
        $benefits->setCellValue('F5', 'Statutory minimum is 12% (13% if monthly wage <= RM5,000)');

        $lookups->fromArray(['JobCode', 'Industry', 'JobFamily', 'StandardJobTitle', 'TypicalLevel'], null, 'A1');
        $lookups->fromArray([['ENG01', 'Manufacturing', 'Engineering & Technical', 'Process Engineer', 'Executive'], ['HR01', 'All', 'Human Resources', 'HR Executive', 'Executive']], null, 'A2');
        $column = 12;
        foreach (self::LISTS as $name => $values) {
            $lookups->setCellValue([$column, 1], $name);
            foreach ($values as $index => $value) {
                $lookups->setCellValue([$column, $index + 2], $value);
            }
            $column++;
        }

        if (! $template) {
            $profile->fromArray([[$company], ['Manufacturing'], ['Selangor'], ['200-499'], ['RM20 million - RM50 million'], ['Local / Malaysian-owned'], [2], ['Private'], ['No']], null, 'B3');

            $rows = $salary ?? [
                ['title' => 'Process Engineer', 'headcount' => 4, 'male' => 3, 'female' => 1, 'median' => $median],
                ['title' => 'HR Executive', 'headcount' => 4, 'male' => 2, 'female' => 2, 'median' => 3100, 'avg_male' => 3000, 'avg_female' => 3300],
            ];
            foreach ($rows as $index => $row) {
                $salarySheet->fromArray([
                    $index + 1, '=1+1', $row['title'], null, 'Executive', $row['headcount'], $row['male'], $row['female'], '=1+1',
                    $row['headcount'], 0, 0, 0, '=1+1', '2-4 yrs', 'No', null, null, $row['median'] ?? 4000,
                    $row['avg_male'] ?? null, $row['avg_female'] ?? null, '=1+1', 1, 200, 0,
                ], null, 'A'.($index + 6));
            }

            $benefits->fromArray(['EPF', 'Yes', 13], null, 'A5');
            $benefits->fromArray(['Outpatient', 'Yes', 'Yes'], null, 'A11');
            $attrition->fromArray([[12.5], [20], [0], [1], [0], ['Engineering & Technical'], ['Human Resources'], [null], [45], ['Growing']], null, 'B3');
            $consentSheet->fromArray([[$consent], ['Aina'], ['HR Manager'], ['30/09/2026']], null, 'B5');
        }

        $path = tempnam(sys_get_temp_dir(), 'survey').'.xlsx';
        (new Xlsx($book))->setPreCalculateFormulas(false)->save($path);

        return new UploadedFile($path, $file ?? ($template ? 'template.xlsx' : 'survey.xlsx'), null, null, true);
    }
}
