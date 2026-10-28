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
            ->assertInertiaFlash('benchmarkUpload.1.status', 'needs_fixing');

        $this->assertSame(['alpha', 'beta'], SurveyCycle::firstOrFail()->participants()->orderBy('company_key')->pluck('company_key')->all());
    }

    public function test_a_cycle_can_be_created_from_completed_workbooks_alone_and_the_blank_template_added_later()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('benchmark.cycles.store'), ['name' => '2025/2026', 'status' => 'open', 'min_companies' => 3])
            ->assertSessionHasErrors('template');
        $this->post(route('benchmark.cycles.store'), [
            // The form sends the unused template field as an empty value.
            'name' => '2025/2026', 'status' => 'open', 'min_companies' => 3, 'template' => '',
            'files' => [$this->workbook('Alpha', file: 'alpha.xlsx', remarks: 'Statutory minimum is 12% (13% if monthly wage <= RM5,000)')],
        ])->assertSessionHasNoErrors()->assertInertiaFlash('benchmarkUpload.0.status', 'imported');

        $cycle = SurveyCycle::firstOrFail();
        $this->assertNull($cycle->template_path);
        $this->assertSame(2, $cycle->jobs()->count(), 'catalogue read from the workbook');
        $remarks = fn () => $cycle->participants()->firstOrFail()->benefits()->where('item', 'epf_employer_rate')->value('remarks');
        $this->assertNotNull($remarks(), 'without the blank template, guidance text cannot be recognised');

        $this->put(route('benchmark.cycles.update', $cycle), ['name' => '2025/2026', 'status' => 'open', 'min_companies' => 3, 'template' => $this->workbook(template: true)])
            ->assertSessionHasNoErrors();
        $this->assertNull($remarks(), 'adding the blank template clears unchanged guidance text');
    }

    public function test_workbooks_are_imported_rejected_skipped_and_replaced()
    {
        $cycle = $this->cycle();
        $this->actingAs($this->userWithRole());

        $notSurvey = UploadedFile::fake()->create('notes.xlsx', 5);
        $this->post(route('benchmark.participants.upload'), ['cycle_id' => $cycle->id, 'files' => [$this->workbook('Alpha Sdn Bhd'), $notSurvey]])
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

    public function test_a_workbook_missing_a_sheet_is_reported_with_the_rest_of_its_errors()
    {
        $cycle = $this->cycle();
        $this->actingAs($this->userWithRole());

        $file = $this->workbook(without: '5. Consent & Submission', salary: [['title' => 'Astronaut', 'headcount' => 1, 'male' => 1, 'female' => 0]]);
        $this->post(route('benchmark.participants.upload'), ['cycle_id' => $cycle->id, 'files' => [$file]])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('benchmarkUpload.0.status', 'needs_fixing')
            ->assertInertiaFlash('benchmarkUpload.0.errors.0', 'The workbook is missing the sheet(s): 5. Consent & Submission. Use the survey template without renaming or deleting sheets.')
            ->assertInertiaFlash('benchmarkUpload.0.errors.1', 'Salary Data row 1 (Astronaut): "Astronaut" is not a Standard Job Title in the catalogue.');

        $this->assertCount(2, SurveyParticipant::firstOrFail()->errors ?? []);
    }

    public function test_a_formula_in_place_of_a_figure_is_reported_with_a_clear_reason()
    {
        $cycle = $this->cycle();
        $this->actingAs($this->userWithRole());

        $file = $this->workbook(salary: [['title' => 'Process Engineer', 'headcount' => 4, 'male' => 3, 'female' => 1, 'median' => '=(R6-Q6)/2+Q6']]);
        $this->post(route('benchmark.participants.upload'), ['cycle_id' => $cycle->id, 'files' => [$file]])
            ->assertInertiaFlash('benchmarkUpload.0.status', 'needs_fixing')
            ->assertInertiaFlash('benchmarkUpload.0.errors', ['Salary Data row 1 (Process Engineer) › Median Base Salary: contains a formula ("=(R6-Q6)/2+Q6"); type the actual figure instead.']);

        $this->assertNull(SurveyParticipant::firstOrFail()->salaryRows()->value('median_salary'));
    }

    public function test_salary_columns_are_found_by_heading_in_older_template_versions()
    {
        $cycle = $this->cycle();
        $this->actingAs($this->userWithRole());

        $this->post(route('benchmark.participants.upload'), ['cycle_id' => $cycle->id, 'files' => [$this->workbook(median: 4800, medianBeforeMax: true)]])
            ->assertInertiaFlash('benchmarkUpload.0.status', 'imported');

        $this->assertEquals(4800, SurveyParticipant::firstOrFail()->salaryRows()->where('job_title', 'Process Engineer')->value('median_salary'));
    }

    public function test_a_filled_row_without_a_standard_job_title_is_reported()
    {
        $cycle = $this->cycle();
        $this->actingAs($this->userWithRole());

        $file = $this->workbook(salary: [['title' => null, 'headcount' => 1, 'male' => 1, 'female' => 0]]);
        $this->post(route('benchmark.participants.upload'), ['cycle_id' => $cycle->id, 'files' => [$file]])
            ->assertInertiaFlash('benchmarkUpload.0.status', 'needs_fixing')
            ->assertInertiaFlash('benchmarkUpload.0.errors.0', 'Salary Data row 1 (—): Standard Job Title is missing; choose one from the catalogue.');
    }

    public function test_a_workbook_with_errors_is_kept_for_follow_up_until_a_clean_file_replaces_it()
    {
        $cycle = $this->cycle();
        $this->actingAs($this->userWithRole());
        $this->upload($cycle, ['Alpha' => 4000]);

        $this->post(route('benchmark.participants.upload'), ['cycle_id' => $cycle->id, 'files' => [$this->workbook('Beta', consent: 'No', median: 9000)]])
            ->assertInertiaFlash('benchmarkUpload.0.status', 'needs_fixing');
        $beta = SurveyParticipant::where('company_key', 'beta')->firstOrFail();
        $this->assertSame(['Consent & Submission › The company has not consented (must be "Yes").'], $beta->errors);
        Storage::disk('local')->assertExists((string) $beta->file_path);

        $this->get(route('benchmark.participants.index', ['status' => 'needs_fixing']))
            ->assertInertia(fn ($page) => $page->where('participants.total', 1)->where('participants.data.0.company_name', 'Beta'));
        $this->get(route('benchmark.analytics.index'))
            ->assertInertia(fn ($page) => $page->where('report.overview.companies', 1));

        $csv = $this->get(route('benchmark.participants.issues', ['cycle' => $cycle->id]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Beta,survey.xlsx,Error,"Consent & Submission › The company has not consented (must be ""Yes"").', $csv);
        $this->assertStringNotContainsString('Alpha,alpha.xlsx,Error', $csv);

        // The corrected file replaces it without ticking "Replace", and it then counts.
        $this->post(route('benchmark.participants.upload'), ['cycle_id' => $cycle->id, 'files' => [$this->workbook('Beta', median: 9000)]])
            ->assertInertiaFlash('benchmarkUpload.0.status', 'replaced');
        $this->assertNull(SurveyParticipant::where('company_key', 'beta')->firstOrFail()->errors);
        $this->get(route('benchmark.analytics.index'))
            ->assertInertia(fn ($page) => $page->where('report.overview.companies', 2));
    }

    public function test_analytics_hide_figures_below_the_threshold_and_pool_the_rest()
    {
        $cycle = $this->cycle();
        $this->actingAs($this->userWithRole());
        $this->upload($cycle, ['Alpha' => 4000, 'Beta' => 5000]);

        $this->get(route('benchmark.analytics.index', ['confidentiality' => 'on']))->assertInertia(fn ($page) => $page
            ->component('benchmark/analytics/index')
            ->where('report.overview.companies', 2)
            ->where('report.salaries.0.job_title', 'Process Engineer')
            ->where('report.salaries.0.suppressed', true)
            ->missing('report.salaries.0.median')
            ->where('report.attrition.suppressed', true));

        // By default the admin sees every figure; the rule is only for reports shared outside.
        $this->get(route('benchmark.analytics.index'))->assertInertia(fn ($page) => $page
            ->where('report.salaries.0.suppressed', false)
            ->where('report.salaries.0.median', 4500)
            ->where('report.attrition.suppressed', false));

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

    public function test_a_submission_can_be_corrected_online_until_it_counts()
    {
        $cycle = $this->cycle();
        $user = $this->userWithRole();
        $this->actingAs($user);
        $this->upload($cycle, ['Alpha' => 4000]);
        $this->post(route('benchmark.participants.upload'), ['cycle_id' => $cycle->id, 'files' => [
            $this->workbook('Beta', consent: 'No', salary: [['title' => 'Astronaut', 'headcount' => 2, 'male' => 1, 'female' => 1]]),
        ]])->assertInertiaFlash('benchmarkUpload.0.status', 'needs_fixing');
        $beta = SurveyParticipant::where('company_key', 'beta')->firstOrFail();
        $row = $beta->salaryRows()->firstOrFail();
        $this->assertSame(1, $row->line);

        $profile = ['company_name' => 'Beta Sdn Bhd', 'industry' => 'Manufacturing', 'state' => 'Selangor', 'employee_band' => '50-99', 'consented' => true];
        $this->put(route('benchmark.participants.profile', $beta), [...$profile, 'industry' => 'Engineering'])->assertSessionHasErrors('industry');
        $this->put(route('benchmark.participants.profile', $beta), [...$profile, 'company_name' => 'ALPHA'])->assertSessionHasErrors('company_name');
        $this->put(route('benchmark.participants.profile', $beta), $profile)->assertSessionHasNoErrors();
        $this->assertSame(['Salary Data row 1 (Astronaut): "Astronaut" is not a Standard Job Title in the catalogue.'], $beta->fresh()?->errors);

        $fixed = [...$this->rowAnswers(), 'job_title' => 'process engineer'];
        $this->put(route('benchmark.participants.rows.update', [$beta, $row]), $fixed)->assertRedirect()->assertSessionHasNoErrors();
        $beta->refresh();
        $this->assertNull($beta->errors);
        $this->assertSame($user->id, $beta->edited_by);
        $this->assertSame(['Process Engineer', 'ENG01', 'Engineering & Technical'], array_values($row->fresh()?->only('job_title', 'job_code', 'job_family') ?? []));
        $this->get(route('benchmark.analytics.index'))->assertInertia(fn ($page) => $page->where('report.overview.companies', 2));

        // The same title and level on another row (e.g. another site) is fine; rows can be added and removed.
        $this->post(route('benchmark.participants.rows.store', $beta), $fixed)->assertRedirect()->assertSessionHasNoErrors();
        $added = $beta->salaryRows()->latest('id')->firstOrFail();
        $this->assertSame(2, $added->line);
        $this->assertNull($beta->fresh()?->errors);
        $this->delete(route('benchmark.participants.rows.destroy', [$beta, $added]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $beta->salaryRows()->count());

        $this->put(route('benchmark.participants.benefits', $beta), ['benefits' => [['item' => 'outpatient_coverage', 'same_for_all' => true, 'company_value' => 'Yes'], ['item' => 'dental_coverage']]])
            ->assertSessionHasNoErrors();
        $this->assertSame(['outpatient_coverage'], $beta->benefits()->pluck('item')->all());
        $this->put(route('benchmark.participants.attrition', $beta), ['attrition_rate' => 150, 'headcount_plan' => 'Growing'])->assertSessionHasNoErrors();
        $this->assertSame(['Attrition & Hiring › Overall staff attrition rate of 150% is above 100%.'], $beta->fresh()?->warnings);

        // Another company's row can't be reached through this submission.
        $alphaRow = SurveyParticipant::where('company_key', 'alpha')->firstOrFail()->salaryRows()->firstOrFail();
        $this->put(route('benchmark.participants.rows.update', [$beta, $alphaRow]), $fixed)->assertNotFound();
    }

    public function test_answers_that_fail_a_check_are_kept_as_typed_and_findings_can_be_accepted()
    {
        $cycle = $this->cycle();
        $this->actingAs($this->userWithRole());
        $this->post(route('benchmark.participants.upload'), ['cycle_id' => $cycle->id, 'files' => [
            $this->workbook('Beta', salary: [['title' => 'Process Engineer', 'headcount' => 4, 'male' => 3, 'female' => 0, 'median' => '=(R6-Q6)/2+Q6']]),
        ]])->assertInertiaFlash('benchmarkUpload.0.status', 'needs_fixing');
        $beta = SurveyParticipant::firstOrFail();
        $row = $beta->salaryRows()->firstOrFail();
        $formula = 'Salary Data row 1 (Process Engineer) › Median Base Salary: contains a formula ("=(R6-Q6)/2+Q6"); type the actual figure instead.';
        $gender = 'Salary Data row 1 (Process Engineer): Male + Female (3) must equal Total Headcount (4).';

        $this->assertSame('=(R6-Q6)/2+Q6', $beta->raw_answers['row:1.median_salary']['value'] ?? null);
        $this->get(route('benchmark.participants.show', $beta))->assertInertia(fn ($page) => $page
            ->where('participant.raw_answers', fn ($raw) => $raw['row:1.median_salary']['value'] === '=(R6-Q6)/2+Q6')
            ->where('issues', fn ($issues) => collect($issues)->contains(fn ($issue) => $issue['key'] === 'gender_total|row:1' && $issue['target'] === 'row:1')
                && collect($issues)->firstWhere('message', $formula)['key'] === null));

        // Only acceptable findings can be accepted; accepting the headcount mismatch leaves the formula open.
        $this->post(route('benchmark.participants.accept', $beta), ['keys' => ['gender_total|row:1', 'nonsense|row:1'], 'accepted' => true])->assertRedirect();
        $this->assertSame(['gender_total|row:1'], $beta->fresh()?->accepted_issues);
        $this->assertSame([$formula], $beta->fresh()?->errors);

        // Saving the row with the formula unchanged keeps it (still flagged); the acceptance survives the re-check.
        $answers = [...$this->rowAnswers(), 'headcount' => 4, 'male' => 3, 'female' => 0, 'tenure_1_2' => 4, 'median_salary' => '=(R6-Q6)/2+Q6'];
        $this->put(route('benchmark.participants.rows.update', [$beta, $row]), $answers)->assertSessionHasNoErrors();
        $this->assertSame([$formula], $beta->fresh()?->errors);
        $this->assertNull($row->fresh()?->median_salary);

        // Typing the figure replaces it; nothing is left open, so it counts.
        $this->put(route('benchmark.participants.rows.update', [$beta, $row]), [...$answers, 'median_salary' => 4200])->assertSessionHasNoErrors();
        $beta->refresh();
        $this->assertNull($beta->errors);
        $this->assertNull($beta->raw_answers);
        $this->get(route('benchmark.analytics.index'))->assertInertia(fn ($page) => $page->where('report.overview.companies', 1));

        $this->post(route('benchmark.participants.accept', $beta), ['keys' => ['gender_total|row:1'], 'accepted' => false])->assertRedirect();
        $this->assertSame([$gender], $beta->fresh()?->errors);
    }

    public function test_the_job_catalogue_can_be_edited_and_changes_carry_through_to_submissions()
    {
        $cycle = $this->cycle();
        $this->actingAs($this->userWithRole());
        $this->get(route('benchmark.jobs.index'))->assertInertia(fn ($page) => $page->component('benchmark/jobs/index')->where('jobs.total', 2));

        $this->post(route('benchmark.participants.upload'), ['cycle_id' => $cycle->id, 'files' => [
            $this->workbook('Beta', salary: [['title' => 'Astronaut', 'headcount' => 1, 'male' => 1, 'female' => 0]]),
        ]])->assertInertiaFlash('benchmarkUpload.0.status', 'needs_fixing');
        $beta = SurveyParticipant::firstOrFail();

        $job = ['survey_cycle_id' => $cycle->id, 'code' => 'SPC01', 'title' => 'Astronaut', 'industry' => 'All', 'job_family' => 'Engineering & Technical'];
        $this->post(route('benchmark.jobs.store'), [...$job, 'code' => 'ENG01'])->assertSessionHasErrors('code');
        $this->post(route('benchmark.jobs.store'), [...$job, 'title' => 'process engineer'])->assertSessionHasErrors('title');

        // Adding the missing title links the company's row to it, and the submission no longer needs fixing.
        $this->post(route('benchmark.jobs.store'), $job)->assertSessionHasNoErrors();
        $this->assertSame(['SPC01', 'Engineering & Technical'], array_values($beta->salaryRows()->firstOrFail()->only('job_code', 'job_family')));
        $this->assertNull($beta->fresh()?->errors);

        // Renaming (and recoding) the job renames it on the rows that use it.
        $astronaut = $cycle->jobs()->where('code', 'SPC01')->firstOrFail();
        $this->put(route('benchmark.jobs.update', $astronaut), [...$job, 'code' => 'SPC02', 'title' => 'Space Engineer'])->assertSessionHasNoErrors();
        $this->assertSame(['Space Engineer', 'SPC02'], array_values($beta->salaryRows()->firstOrFail()->only('job_title', 'job_code')));
        $this->assertNull($beta->fresh()?->errors);

        // A job in use can't be deleted; an unused one can.
        $this->delete(route('benchmark.jobs.destroy', $astronaut))->assertRedirect();
        $this->assertModelExists($astronaut);
        $hr = $cycle->jobs()->where('code', 'HR01')->firstOrFail();
        $this->delete(route('benchmark.jobs.destroy', $hr))->assertRedirect();
        $this->assertModelMissing($hr);

        $this->actingAs($this->userWithRole('hr'));
        $this->post(route('benchmark.jobs.store'), [...$job, 'code' => 'X1', 'title' => 'Other'])->assertForbidden();
        $this->delete(route('benchmark.jobs.destroy', $astronaut))->assertForbidden();
    }

    public function test_only_uploaders_can_correct_a_submission()
    {
        $cycle = $this->cycle();
        $this->upload($cycle, ['Alpha' => 4000]);
        $alpha = SurveyParticipant::firstOrFail();

        $this->actingAs($this->userWithRole('hr'));
        $this->put(route('benchmark.participants.profile', $alpha), ['company_name' => 'Changed', 'consented' => true])->assertForbidden();
        $this->post(route('benchmark.participants.rows.store', $alpha), $this->rowAnswers())->assertForbidden();
        $this->assertSame('Alpha', $alpha->fresh()?->company_name);
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

    /**
     * @return array<string, mixed>
     */
    private function rowAnswers(): array
    {
        return [
            'job_title' => 'Process Engineer', 'job_level' => 'Executive', 'headcount' => 2, 'male' => 1, 'female' => 1,
            'tenure_under_1' => 0, 'tenure_1_2' => 2, 'tenure_3_4' => 0, 'tenure_5_plus' => 0,
            'median_salary' => 4200, 'avg_male' => 4100, 'avg_female' => 4300,
        ];
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
    private function workbook(string $company = 'Alpha Sdn Bhd', bool $template = false, ?array $salary = null, string $consent = 'Yes', int $median = 4500, ?string $file = null, ?string $remarks = null, ?string $without = null, bool $medianBeforeMax = false): UploadedFile
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
        $salarySheet->fromArray($medianBeforeMax ? ["Min\nBase Salary", "Median\nBase Salary", "Max\nBase Salary"] : ["Min\nBase Salary", "Max\nBase Salary", "Median\nBase Salary"], null, 'Q4');
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
                    $row['headcount'], 0, 0, 0, '=1+1', '2-4 yrs', 'No', null, $medianBeforeMax ? ($row['median'] ?? 4000) : null, $medianBeforeMax ? null : ($row['median'] ?? 4000),
                    $row['avg_male'] ?? null, $row['avg_female'] ?? null, '=1+1', 1, 200, 0,
                ], null, 'A'.($index + 6));
            }

            $benefits->fromArray(['EPF', 'Yes', 13], null, 'A5');
            if ($remarks !== null) {
                $benefits->setCellValue('F5', $remarks);
            }
            $benefits->fromArray(['Outpatient', 'Yes', 'Yes'], null, 'A11');
            $attrition->fromArray([[12.5], [20], [0], [1], [0], ['Engineering & Technical'], ['Human Resources'], [null], [45], ['Growing']], null, 'B3');
            $consentSheet->fromArray([[$consent], ['Aina'], ['HR Manager'], ['30/09/2026']], null, 'B5');
        }

        if ($without !== null) {
            $book->removeSheetByIndex($book->getIndex($book->getSheetByNameOrThrow($without)));
        }

        $path = tempnam(sys_get_temp_dir(), 'survey').'.xlsx';
        (new Xlsx($book))->setPreCalculateFormulas(false)->save($path);

        return new UploadedFile($path, $file ?? ($template ? 'template.xlsx' : 'survey.xlsx'), null, null, true);
    }
}
