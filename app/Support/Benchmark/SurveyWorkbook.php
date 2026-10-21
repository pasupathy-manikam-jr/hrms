<?php

namespace App\Support\Benchmark;

use App\Models\Benchmark\SurveyCycle;
use App\Models\Benchmark\SurveyParticipant;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Reads the Malaysia Salary & Benefits Benchmark Survey workbook by cell position: the blank template
 * (dropdown lists and job catalogue) and each company's completed copy (answers, errors and warnings).
 * The workbook's own auto columns (job family, averages, checks, totals) are recalculated, never trusted.
 */
class SurveyWorkbook
{
    public const PROFILE = '1. Company Profile';

    public const SALARY = '2. Salary Data';

    public const BENEFITS = '3. Benefits';

    public const ATTRITION = '4. Attrition & Hiring';

    public const CONSENT = '5. Consent & Submission';

    public const LOOKUPS = 'Lookups';

    /** Salary Data rows that hold answers (row 5 is the template's "Ex." example). */
    private const SALARY_ROWS = [6, 64];

    /** Benefits sheet row => [key, label]. Rows 4, 10, 21 and 28 are section headings. */
    public const BENEFIT_ITEMS = [
        5 => ['epf_employer_rate', 'EPF employer contribution rate (%)'],
        6 => ['annual_leave_days', 'Annual leave (days/year)'],
        7 => ['medical_leave_days', 'Medical/sick leave (days/year)'],
        8 => ['maternity_leave_days', 'Maternity leave (days)'],
        9 => ['paternity_leave_days', 'Paternity leave (days)'],
        11 => ['outpatient_coverage', 'Outpatient medical coverage provided?'],
        12 => ['outpatient_limit', 'Outpatient annual limit (RM)'],
        13 => ['hospitalisation_coverage', 'Hospitalisation / inpatient coverage provided?'],
        14 => ['hospitalisation_limit', 'Hospitalisation annual/lifetime limit (RM)'],
        15 => ['dental_coverage', 'Dental coverage provided?'],
        16 => ['dental_amount', 'Dental coverage amount (RM)'],
        17 => ['optical_coverage', 'Optical coverage provided?'],
        18 => ['optical_amount', 'Optical coverage amount (RM)'],
        19 => ['group_life_insurance', 'Group life insurance provided?'],
        20 => ['personal_accident_insurance', 'Personal accident insurance provided?'],
        22 => ['variable_bonus_months', 'Variable / performance bonus paid (months)'],
        23 => ['kpi_incentive_scheme', 'KPI / sales / production incentive scheme?'],
        24 => ['profit_sharing_scheme', 'Profit-sharing / company-wide incentive scheme?'],
        25 => ['employee_share_scheme', 'Employee Share Scheme (ESS) / stock options?'],
        26 => ['other_incentive_notes', 'Other incentive notes'],
        27 => ['target_bonus_percent', 'Target bonus % for 2027'],
        29 => ['flexible_work', 'Flexible / hybrid work arrangement?'],
        30 => ['other_benefit', 'Other notable benefit'],
    ];

    /** Attrition & Hiring column B rows => field. */
    public const ATTRITION_FIELDS = [
        3 => 'attrition_rate', 4 => 'new_hire_attrition_rate', 5 => 'retirements', 6 => 'involuntary_terminations',
        7 => 'contract_non_renewals', 8 => 'hardest_to_hire_1', 9 => 'hardest_to_hire_2', 10 => 'hardest_to_hire_3',
        11 => 'time_to_fill_days', 12 => 'headcount_plan', 13 => 'hardest_to_retain_1', 14 => 'hardest_to_retain_2',
        15 => 'hardest_to_retain_3', 16 => 'retention_initiatives', 17 => 'pay_reason_for_leaving',
        18 => 'other_leaving_reasons', 19 => 'retrenched', 20 => 'retrenchment_driver',
        21 => 'retrenchment_above_statutory', 22 => 'retrenchment_above_note', 23 => 'comments',
    ];

    /**
     * Rules whose finding can be accepted as it stands: consistency checks and every warning. Missing or invalid
     * answers can't be, since the analytics can't use the row without them.
     */
    public const ACCEPTABLE_RULES = ['gender_total', 'tenure_total', 'min_above_median', 'max_below_median'];

    /** @var list<array{type: string, message: string, target: string|null, rule: string|null}> */
    private array $issues = [];

    /**
     * Answers that failed a check, kept as the company typed them (the column is saved empty), keyed "target.field".
     *
     * @var array<string, array{value: string, message: string, rule: string|null}>
     */
    private array $raw = [];

    /** What the next issue is about: "row:7" (Salary Data line), "salary", "benefit:<item>", "attrition:<field>", "profile" or "consent". */
    private ?string $target = null;

    /**
     * The blank template's dropdown lists, job catalogue and the Benefits sheet's guidance text
     * (used to recognise remarks the company left unchanged).
     *
     * @return array{lookups: array<string, list<string>>, jobs: list<array<string, string|null>>}
     */
    public static function readTemplate(string $path, bool $blank = true): array
    {
        $book = self::load($path, [self::LOOKUPS, self::BENEFITS, self::SALARY]);
        self::assertTemplate($book);
        $sheet = $book->getSheetByNameOrThrow(self::LOOKUPS);

        $lookups = [];
        $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        for ($column = 12; $column <= $lastColumn; $column++) {
            $name = self::text($sheet->getCell([$column, 1])->getValue());
            // Per-industry title lists repeat the catalogue, which is stored in full below.
            if ($name === null || str_starts_with($name, 'Titles_') || $name === 'JobTitleList') {
                continue;
            }
            $values = [];
            for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
                if (($value = self::text($sheet->getCell([$column, $row])->getValue())) !== null) {
                    $values[] = $value;
                }
            }
            $lookups[$name] = $values;
        }

        $jobs = [];
        for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
            $code = self::text($sheet->getCell([1, $row])->getValue());
            if ($code === null) {
                continue;
            }
            $cell = fn (int $column) => self::text($sheet->getCell([$column, $row])->getValue());
            $jobs[] = [
                'code' => $code, 'industry' => $cell(2), 'job_family' => $cell(3), 'title' => $cell(4),
                'typical_level' => $cell(5), 'summary' => $cell(6), 'responsibilities' => $cell(7),
                'requirements' => $cell(8), 'masco_group' => $cell(9), 'masco_reference' => $cell(10),
            ];
        }

        // In a completed workbook the Remarks column holds the company's answers, not the template's guidance.
        $benefits = $book->getSheetByNameOrThrow(self::BENEFITS);
        $lookups['_benefit_guidance'] = $blank ? array_values(array_filter(array_map(
            fn (int $row) => self::text($benefits->getCell("F{$row}")->getValue()),
            array_keys(self::BENEFIT_ITEMS),
        ))) : [];
        $book->disconnectWorksheets();

        return ['lookups' => $lookups, 'jobs' => $jobs];
    }

    /**
     * One company's completed workbook, checked against the cycle's lists and catalogue.
     *
     * @param  array<string, array<string, mixed>>  $catalogue  job title => catalogue entry (code, job_family, title)
     * @return array{participant: array<string, mixed>, salaryRows: list<array<string, mixed>>, benefits: list<array<string, mixed>>, attrition: array<string, mixed>, issues: list<array{type: string, message: string, target: string|null, rule: string|null}>, raw: array<string, array{value: string, message: string, rule: string|null}>, errors: list<string>, warnings: list<string>}
     */
    public function read(string $path, SurveyCycle $cycle, array $catalogue): array
    {
        $this->issues = [];
        $this->raw = [];
        $this->target = null;

        try {
            $sheets = [self::PROFILE, self::SALARY, self::BENEFITS, self::ATTRITION, self::CONSENT];
            $book = self::load($path, $sheets);
            self::assertTemplate($book);
        } catch (Throwable $e) {
            return ['participant' => [], 'salaryRows' => [], 'benefits' => [], 'attrition' => [], 'issues' => [], 'raw' => [], 'errors' => [$e->getMessage()], 'warnings' => []];
        }

        // A missing sheet is one error; the sheets that are there are still checked, so the report lists everything to fix.
        if ($missing = array_diff($sheets, $book->getSheetNames())) {
            $this->fail(__('The workbook is missing the sheet(s): :sheets. Use the survey template without renaming or deleting sheets.', ['sheets' => implode(', ', $missing)]));
        }
        $sheet = fn (string $name, callable $read, mixed $empty) => ($found = $book->getSheetByName($name)) ? $read($found) : $empty;

        $participant = $sheet(self::PROFILE, fn (Worksheet $found) => $this->readProfile($found, $cycle), [])
            + $sheet(self::CONSENT, fn (Worksheet $found) => $this->readConsent($found), []);

        $result = [
            'participant' => $participant,
            'salaryRows' => $this->readSalaries($book->getSheetByNameOrThrow(self::SALARY), $cycle, $catalogue),
            'benefits' => $sheet(self::BENEFITS, fn (Worksheet $found) => $this->readBenefits($found, $cycle), []),
            'attrition' => $sheet(self::ATTRITION, fn (Worksheet $found) => $this->readAttrition($found, $cycle), []),
            'issues' => $this->issues,
            'raw' => $this->raw,
            ...self::summary($this->issues),
        ];
        // Workbooks hold circular references; free each one so a multi-file upload stays within memory_limit.
        $book->disconnectWorksheets();

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function readProfile(Worksheet $sheet, SurveyCycle $cycle): array
    {
        $this->target = 'profile';
        $value = fn (int $row) => self::text($sheet->getCell("B{$row}")->getValue());

        $profile = [
            'company_name' => $value(3),
            'industry' => $this->choice($value(4), $cycle->lookup('IndustryList'), 'Company Profile › Industry', required: true, key: 'industry'),
            'state' => $this->choice($value(5), $cycle->lookup('StateList'), 'Company Profile › State', required: true, key: 'state'),
            'employee_band' => $this->choice($value(6), $cycle->lookup('EmployeeBandList'), 'Company Profile › Number of Employees', required: true, key: 'employee_band'),
            'revenue_band' => $this->choice($value(7), $cycle->lookup('RevenueBandList'), 'Company Profile › Annual Revenue Band', key: 'revenue_band'),
            'ownership_type' => $this->choice($value(8), $cycle->lookup('OwnershipTypeList'), 'Company Profile › Ownership Type', key: 'ownership_type'),
            'locations' => $this->integer($sheet->getCell('B9')->getValue(), 'Company Profile › Operating Locations', key: 'locations'),
            'listed_status' => $this->choice($value(10), $cycle->lookup('ListedStatusList'), 'Company Profile › Listed Status', key: 'listed_status'),
            'unionised' => $this->choice($value(11), $cycle->lookup('YesNo'), 'Company Profile › Unionised Workforce', key: 'unionised'),
        ];

        if ($profile['company_name'] === null) {
            $this->fail(__('Company Profile › Company Name is missing.'));
        }

        return $profile;
    }

    /**
     * @return array<string, mixed>
     */
    private function readConsent(Worksheet $sheet): array
    {
        $this->target = 'consent';
        $consented = strcasecmp((string) self::text($sheet->getCell('B5')->getValue()), 'Yes') === 0;
        $this->checkConsent($consented);

        return [
            'consented' => $consented,
            'authorised_name' => self::text($sheet->getCell('B6')->getValue()),
            'authorised_designation' => self::text($sheet->getCell('B7')->getValue()),
            'consent_date' => $this->date($sheet->getCell('B8')->getValue(), key: 'consent_date'),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $catalogue
     * @return list<array<string, mixed>>
     */
    private function readSalaries(Worksheet $sheet, SurveyCycle $cycle, array $catalogue): array
    {
        $rows = [];
        // Template versions differ in the order of Min / Max / Median, so find them by their headings.
        $pay = ['min' => 'Q', 'max' => 'R', 'median' => 'S'];
        foreach (['Q', 'R', 'S'] as $column) {
            $heading = mb_strtolower((string) self::text($sheet->getCell("{$column}4")->getValue()));
            foreach (array_keys($pay) as $key) {
                if (str_starts_with($heading, $key)) {
                    $pay[$key] = $column;
                }
            }
        }

        for ($row = self::SALARY_ROWS[0]; $row <= self::SALARY_ROWS[1]; $row++) {
            $cell = fn (string $column) => $sheet->getCell("{$column}{$row}")->getValue();
            $title = self::text($cell('C'));

            // A filled row without a Standard Job Title is kept (and flagged) so it can be corrected online.
            if ($title === null && self::text($cell('D')) === null && self::text($cell('F')) === null && self::text($cell($pay['median'])) === null) {
                continue;
            }

            $this->target = 'row:'.($row - 5);
            $at = self::rowLabel($row - 5, $title ?? self::text($cell('D')));
            $job = $this->checkTitle((string) $title, $at, $catalogue);
            $level = $this->choice(self::text($cell('E')), $cycle->lookup('JobLevelList'), "{$at} › Job Level", required: true, key: 'job_level');
            $number = fn (string $column, string $label, string $key) => $this->number($cell($column), "{$at} › {$label}", $key);
            $count = fn (string $column, string $label, string $key) => (int) ($this->number($cell($column), "{$at} › {$label}", $key) ?? 0);

            $data = [
                'line' => $row - 5,
                'job_code' => $job['code'] ?? null,
                'job_family' => $job['job_family'] ?? '',
                'job_title' => $job['title'] ?? (string) $title,
                'own_title' => self::text($cell('D')),
                'job_level' => $level ?? '',
                'headcount' => $count('F', 'Total Headcount', 'headcount'),
                'male' => $count('G', 'Male', 'male'),
                'female' => $count('H', 'Female', 'female'),
                'tenure_under_1' => $count('J', 'Tenure <1 yr', 'tenure_under_1'),
                'tenure_1_2' => $count('K', 'Tenure 1-2 yrs', 'tenure_1_2'),
                'tenure_3_4' => $count('L', 'Tenure 3-4 yrs', 'tenure_3_4'),
                'tenure_5_plus' => $count('M', 'Tenure 5+ yrs', 'tenure_5_plus'),
                'experience_required' => $this->choice(self::text($cell('O')), $cycle->lookup('ExperienceRequiredList'), "{$at} › Typical Experience", key: 'experience_required'),
                'shift_based' => ($shift = $this->choice(self::text($cell('P')), $cycle->lookup('YesNo'), "{$at} › Shift-Based", key: 'shift_based')) === null ? null : $shift === 'Yes',
                'min_salary' => $number($pay['min'], 'Min Base Salary', 'min_salary'),
                'max_salary' => $number($pay['max'], 'Max Base Salary', 'max_salary'),
                'median_salary' => $number($pay['median'], 'Median Base Salary', 'median_salary'),
                'avg_male' => $number('T', 'Avg. Male Base Salary', 'avg_male'),
                'avg_female' => $number('U', 'Avg. Female Base Salary', 'avg_female'),
                'guaranteed_bonus_months' => $number('W', 'Guaranteed Annual Bonus', 'guaranteed_bonus_months'),
                'allowance_transport' => $number('X', 'Transport', 'allowance_transport'),
                'allowance_meal' => $number('Y', 'Meal', 'allowance_meal'),
                'allowance_housing' => $number('Z', 'Housing', 'allowance_housing'),
                'allowance_shift' => $number('AA', 'Shift / Night Differential', 'allowance_shift'),
                'allowance_phone' => $number('AB', 'Phone / Internet', 'allowance_phone'),
                'allowance_overtime' => $number('AC', 'Overtime', 'allowance_overtime'),
                'allowance_outstation' => $number('AD', 'Outstation / Travel', 'allowance_outstation'),
                'allowance_other' => $number('AE', 'Other Allowance', 'allowance_other'),
                'other_allowance_note' => self::text($cell('AF')),
            ];

            $this->checkSalaryRow($data, $at, trim((string) $cell($pay['median'])) !== '');
            $rows[] = self::withTotals($data);
        }

        $this->target = 'salary';
        if ($rows === []) {
            $this->fail(__('Salary Data › No job rows were filled in.'));
        }

        return $rows;
    }

    /**
     * Re-checks a saved submission (after it was corrected online) with the rules an upload applies to the same
     * answers. Answers that weren't valid were saved empty, so they surface here as missing.
     *
     * @param  array<string, array<string, mixed>>  $catalogue  lower-cased job title => catalogue entry
     * @return array{issues: list<array{type: string, message: string, target: string|null, rule: string|null}>, errors: list<string>, warnings: list<string>}
     */
    public function check(SurveyParticipant $participant, array $catalogue): array
    {
        $this->issues = [];
        $this->raw = [];
        $this->target = null;

        // Answers still as the company typed them are reported again, and stand in for "missing" on their field.
        $raw = $participant->raw_answers ?? [];
        $this->target = 'profile';
        if (blank($participant->company_name)) {
            $this->fail(__('Company Profile › Company Name is missing.'));
        }
        foreach (['industry' => 'Industry', 'state' => 'State', 'employee_band' => 'Number of Employees'] as $field => $label) {
            if (blank($participant->{$field}) && ! isset($raw["profile.{$field}"])) {
                $this->fail(__(':field is missing.', ['field' => "Company Profile › {$label}"]));
            }
        }
        $this->target = 'consent';
        $this->checkConsent($participant->consented === true);

        $rows = $participant->salaryRows()->orderBy('line')->orderBy('id')->get();
        foreach ($rows as $index => $saved) {
            $row = [
                ...$saved->only(['job_title', 'job_level', 'guaranteed_bonus_months']),
                ...array_map('intval', $saved->only(['headcount', 'male', 'female', 'tenure_under_1', 'tenure_1_2', 'tenure_3_4', 'tenure_5_plus'])),
                ...array_map(fn ($value) => $value === null ? null : (float) $value, $saved->only(['min_salary', 'max_salary', 'median_salary', 'avg_male', 'avg_female'])),
            ];
            $this->target = 'row:'.($saved->line ?? $index + 1);
            $at = self::rowLabel($saved->line ?? $index + 1, $row['job_title'] ?: $saved->own_title);
            $this->checkTitle($row['job_title'], $at, $catalogue);
            if (blank($row['job_level']) && ! isset($raw["{$this->target}.job_level"])) {
                $this->fail(__(':field is missing.', ['field' => "{$at} › Job Level"]));
            }
            $this->checkSalaryRow($row, $at, isset($raw["{$this->target}.median_salary"]));
        }
        $this->target = 'salary';
        if ($rows->isEmpty()) {
            $this->fail(__('Salary Data › No job rows were filled in.'));
        }

        if ($attrition = $participant->attrition) {
            foreach (['attrition_rate' => 'Overall staff attrition rate', 'new_hire_attrition_rate' => 'New-hire attrition rate'] as $field => $label) {
                $this->target = "attrition:{$field}";
                if ((float) $attrition->{$field} > 100) {
                    $this->warn(__(':field of :value% is above 100%.', ['field' => "Attrition & Hiring › {$label}", 'value' => (float) $attrition->{$field}]), 'over_100');
                }
            }
            $this->checkAttrition($attrition->toArray());
        }

        foreach ($raw as $key => $answer) {
            $this->target = substr($key, 0, (int) strrpos($key, '.'));
            $answer['rule'] === null ? $this->fail($answer['message']) : $this->warn($answer['message'], $answer['rule']);
        }

        return ['issues' => $this->issues, ...self::summary($this->issues)];
    }

    /**
     * The key an accepted finding is stored under ("rule|target"), or null when it can't be accepted.
     *
     * @param  array{type: string, rule: string|null, target: string|null}  $issue
     */
    public static function issueKey(array $issue): ?string
    {
        $acceptable = $issue['type'] === 'warning' || in_array($issue['rule'], self::ACCEPTABLE_RULES, true);

        return $acceptable && $issue['rule'] !== null && $issue['target'] !== null ? "{$issue['rule']}|{$issue['target']}" : null;
    }

    /**
     * The messages still open once accepted findings are set aside.
     *
     * @param  list<array{type: string, message: string, target: string|null, rule: string|null}>  $issues
     * @param  list<string>  $accepted  issue keys
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public static function summary(array $issues, array $accepted = []): array
    {
        $open = array_filter($issues, fn (array $issue) => ! in_array(self::issueKey($issue), $accepted, true));
        $messages = fn (string $type) => array_values(array_map(
            fn (array $issue) => $issue['message'],
            array_filter($open, fn (array $issue) => $issue['type'] === $type),
        ));

        return ['errors' => $messages('error'), 'warnings' => $messages('warning')];
    }

    /**
     * An answer that failed a check: reported, and kept as typed so it can be shown and corrected. A rule makes it a
     * warning (the answer is only doubtful), otherwise it is an error.
     */
    private function invalid(string $message, ?string $key, mixed $value, ?string $rule = null): void
    {
        $rule === null ? $this->fail($message) : $this->warn($message, $rule);

        if ($key !== null && $this->target !== null) {
            $this->raw["{$this->target}.{$key}"] = ['value' => (string) $value, 'message' => $message, 'rule' => $rule];
        }
    }

    private function fail(string $message, ?string $rule = null): void
    {
        $this->issues[] = ['type' => 'error', 'message' => $message, 'target' => $this->target, 'rule' => $rule];
    }

    private function warn(string $message, ?string $rule = null): void
    {
        $this->issues[] = ['type' => 'warning', 'message' => $message, 'target' => $this->target, 'rule' => $rule];
    }

    private static function rowLabel(int $line, ?string $title): string
    {
        return __('Salary Data row :row (:title)', ['row' => $line, 'title' => $title ?: '—']);
    }

    private function checkConsent(bool $consented): void
    {
        if (! $consented) {
            $this->fail(__('Consent & Submission › The company has not consented (must be "Yes").'));
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $catalogue
     * @return array<string, mixed>|null the catalogue entry
     */
    private function checkTitle(string $title, string $at, array $catalogue): ?array
    {
        if ($title === '') {
            $this->fail(__(':at: Standard Job Title is missing; choose one from the catalogue.', ['at' => $at]));

            return null;
        }

        $job = $catalogue[mb_strtolower($title)] ?? null;
        if ($job === null) {
            $this->fail(__(':at: ":title" is not a Standard Job Title in the catalogue.', ['at' => $at, 'title' => $title]));
        }

        return $job;
    }

    /**
     * The workbook's own Check columns and sanity rules.
     *
     * @param  array<string, mixed>  $row
     * @param  bool  $medianFilled  false when the cell is empty; a filled but invalid cell is already reported
     */
    private function checkSalaryRow(array $row, string $at, bool $medianFilled): void
    {
        if ($row['headcount'] < 1) {
            $this->fail(__(':at: Total Headcount must be at least 1.', ['at' => $at]));
        }
        if ($row['headcount'] !== $row['male'] + $row['female']) {
            $this->fail(__(':at: Male + Female (:sum) must equal Total Headcount (:total).', ['at' => $at, 'sum' => $row['male'] + $row['female'], 'total' => $row['headcount']]), 'gender_total');
        }
        $tenure = $row['tenure_under_1'] + $row['tenure_1_2'] + $row['tenure_3_4'] + $row['tenure_5_plus'];
        if ($tenure !== $row['headcount']) {
            $this->fail(__(':at: Tenure headcounts (:sum) must equal Total Headcount (:total).', ['at' => $at, 'sum' => $tenure, 'total' => $row['headcount']]), 'tenure_total');
        }
        if ($row['median_salary'] === null ? ! $medianFilled : $row['median_salary'] <= 0) {
            $this->fail(__(':at: Median Base Salary is required.', ['at' => $at]));
        }
        if ($row['min_salary'] !== null && $row['median_salary'] !== null && $row['min_salary'] > $row['median_salary']) {
            $this->fail(__(':at: Min Base Salary is above the Median.', ['at' => $at]), 'min_above_median');
        }
        if ($row['max_salary'] !== null && $row['median_salary'] !== null && $row['max_salary'] < $row['median_salary']) {
            $this->fail(__(':at: Max Base Salary is below the Median.', ['at' => $at]), 'max_below_median');
        }
        if ($row['male'] > 0 && $row['avg_male'] === null) {
            $this->warn(__(':at: Avg. Male Base Salary is missing although there are male employees.', ['at' => $at]), 'avg_male');
        }
        if ($row['female'] > 0 && $row['avg_female'] === null) {
            $this->warn(__(':at: Avg. Female Base Salary is missing although there are female employees.', ['at' => $at]), 'avg_female');
        }
        if (($row['guaranteed_bonus_months'] ?? 0) > 6) {
            $this->warn(__(':at: Guaranteed bonus of :months months looks high.', ['at' => $at, 'months' => $row['guaranteed_bonus_months']]), 'bonus_high');
        }
    }

    /**
     * Recalculates the workbook's auto columns: the headcount-weighted average and total allowances.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function withTotals(array $row): array
    {
        $weight = ($row['avg_male'] !== null ? $row['male'] : 0) + ($row['avg_female'] !== null ? $row['female'] : 0);
        $row['avg_overall'] = $weight > 0
            ? round((($row['avg_male'] ?? 0) * $row['male'] + ($row['avg_female'] ?? 0) * $row['female']) / $weight, 2)
            : $row['median_salary'];
        $row['total_allowances'] = round(array_sum(array_map(
            fn (string $type) => $row["allowance_{$type}"] ?? 0,
            ['transport', 'meal', 'housing', 'shift', 'phone', 'overtime', 'outstation', 'other'],
        )), 2);

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readBenefits(Worksheet $sheet, SurveyCycle $cycle): array
    {
        $benefits = [];

        foreach (self::BENEFIT_ITEMS as $row => [$key, $label]) {
            $this->target = "benefit:{$key}";
            $cell = fn (string $column) => self::text($sheet->getCell("{$column}{$row}")->getValue());
            $same = $this->choice($cell('B'), $cycle->lookup('YesNo'), "Benefits › {$label} › Same for all job levels?", key: 'same_for_all');
            $remarks = $cell('F');

            // Remarks left as the template's own guidance text aren't the company's answer.
            if ($remarks !== null && self::isGuidance($remarks, $cycle)) {
                $remarks = null;
            }

            $answer = [
                'item' => $key,
                'same_for_all' => $same === null ? null : $same === 'Yes',
                'company_value' => $cell('C'),
                'exec_value' => $cell('D'),
                'manager_value' => $cell('E'),
                'remarks' => $remarks,
            ];

            if ($answer['same_for_all'] === null && $answer['company_value'] === null && $answer['exec_value'] === null && $answer['manager_value'] === null && $remarks === null) {
                continue;
            }

            $benefits[] = $answer;
        }

        return $benefits;
    }

    /**
     * @return array<string, mixed>
     */
    private function readAttrition(Worksheet $sheet, SurveyCycle $cycle): array
    {
        $value = fn (int $row) => $sheet->getCell("B{$row}")->getValue();
        $families = $cycle->lookup('JobFamilyList');
        /** @var array<string, mixed> $answers */
        $answers = [];

        foreach (self::ATTRITION_FIELDS as $row => $field) {
            $this->target = "attrition:{$field}";
            // Name the question as the company sees it in column A.
            $label = 'Attrition & Hiring › '.(self::text($sheet->getCell("A{$row}")->getValue()) ?? str_replace('_', ' ', $field));
            $answers[$field] = match ($field) {
                'attrition_rate', 'new_hire_attrition_rate' => $this->percent($value($row), $label, $field),
                'retirements', 'involuntary_terminations', 'contract_non_renewals', 'time_to_fill_days' => $this->integer($value($row), $label, $field),
                'hardest_to_hire_1', 'hardest_to_hire_2', 'hardest_to_hire_3', 'hardest_to_retain_1', 'hardest_to_retain_2', 'hardest_to_retain_3' => $this->choice(self::text($value($row)), $families, $label, key: $field),
                'headcount_plan' => $this->choice(self::text($value($row)), $cycle->lookup('HeadcountPlanList'), $label, key: $field),
                'pay_reason_for_leaving' => $this->choice(self::text($value($row)), $cycle->lookup('YesNoUnsure'), $label, key: $field),
                'retrenched' => ($retrenched = $this->choice(self::text($value($row)), $cycle->lookup('YesNo'), $label, key: $field)) === null ? null : $retrenched === 'Yes',
                'retrenchment_driver' => $this->choice(self::text($value($row)), $cycle->lookup('RetrenchmentDriverList'), $label, key: $field),
                'retrenchment_above_statutory' => $this->choice(self::text($value($row)), $cycle->lookup('YesNoNA'), $label, key: $field),
                default => self::text($value($row)),
            };
        }

        $this->checkAttrition($answers);

        return $answers;
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function checkAttrition(array $answers): void
    {
        foreach (['hardest_to_hire', 'hardest_to_retain'] as $group) {
            $this->target = "attrition:{$group}";
            $ranked = array_filter([$answers["{$group}_1"] ?? null, $answers["{$group}_2"] ?? null, $answers["{$group}_3"] ?? null]);
            if (count($ranked) !== count(array_unique($ranked))) {
                $this->warn(__('Attrition & Hiring › The same job function is ranked more than once (:group).', ['group' => str_replace('_', ' ', $group)]), 'ranked_twice');
            }
        }

        $this->target = 'attrition:retrenchment_driver';
        if (($answers['retrenched'] ?? null) === true && ($answers['retrenchment_driver'] ?? null) === null) {
            $this->warn(__('Attrition & Hiring › Retrenchment is "Yes" but no driver was given.'), 'retrenchment_driver');
        }
    }

    /**
     * An answer that must be one of the template's dropdown values (case-insensitive); returns the list's spelling.
     *
     * @param  list<string>  $options
     */
    private function choice(?string $value, array $options, string $field, bool $required = false, ?string $key = null): ?string
    {
        if ($value === null) {
            if ($required) {
                $this->fail(__(':field is missing.', ['field' => $field]));
            }

            return null;
        }

        foreach ($options as $option) {
            if (strcasecmp(self::squash($option), self::squash($value)) === 0) {
                return $option;
            }
        }

        if ($options === []) {
            return $value;
        }

        // Name the options so the company can fix it from the issues list alone; long lists stay in the workbook's dropdown.
        $this->invalid(count($options) <= 30
            ? __(':field: ":value" is not one of the allowed options. Choose one of: :options.', ['field' => $field, 'value' => $value, 'options' => implode('; ', $options)])
            : __(':field: ":value" is not one of the allowed options. Choose one from the dropdown in the workbook.', ['field' => $field, 'value' => $value]), $key, $value);

        return null;
    }

    private function number(mixed $value, string $field, ?string $key = null): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $typed = $value;
        if (is_string($value)) {
            $value = str_replace([',', 'RM', 'rm', ' '], '', $value);
        }

        if (is_string($value) && str_starts_with($value, '=')) {
            $this->invalid(__(':field: contains a formula (":value"); type the actual figure instead.', ['field' => $field, 'value' => $typed]), $key, $typed);

            return null;
        }

        if (! is_numeric($value)) {
            $this->invalid(__(':field: ":value" is not a number.', ['field' => $field, 'value' => $typed]), $key, $typed);

            return null;
        }

        if ((float) $value < 0) {
            $this->invalid(__(':field cannot be negative.', ['field' => $field]), $key, $typed);

            return null;
        }

        return round((float) $value, 2);
    }

    private function integer(mixed $value, string $field, ?string $key = null): ?int
    {
        $number = $this->number($value, $field, $key);

        return $number === null ? null : (int) round($number);
    }

    private function percent(mixed $value, string $field, ?string $key = null): ?float
    {
        $number = $this->number(is_string($value) ? rtrim($value, '%') : $value, $field, $key);

        if ($number !== null && $number > 100) {
            $this->warn(__(':field of :value% is above 100%.', ['field' => $field, 'value' => $number]), 'over_100');
        }

        return $number;
    }

    private function date(mixed $value, ?string $key = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return is_numeric($value)
                ? CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString()
                : CarbonImmutable::createFromFormat('d/m/Y', trim((string) $value))?->toDateString();
        } catch (Throwable) {
            $this->invalid(__('Consent & Submission › Date ":value" is not in DD/MM/YYYY format.', ['value' => $value]), $key, $value, 'date_format');

            return null;
        }
    }

    /**
     * @param  list<string>  $sheets
     */
    private static function load(string $path, array $sheets): Spreadsheet
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly($sheets);
        $reader->setReadEmptyCells(false);

        return $reader->load($path);
    }

    /**
     * Refuses files that aren't this survey's workbook, before reading any answers.
     */
    private static function assertTemplate(Spreadsheet $book): void
    {
        $names = $book->getSheetNames();
        $salary = $book->getSheetByName(self::SALARY);

        if (! in_array(self::SALARY, $names, true) || $salary === null || self::text($salary->getCell('C4')->getValue()) === null
            || ! str_contains((string) $salary->getCell('C4')->getValue(), 'Standard Job Title')) {
            throw new \RuntimeException(__('This file is not the Salary & Benefits Benchmark Survey workbook.'));
        }
    }

    private static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        $text = trim((string) $value);

        return $text === '' || str_starts_with($text, '=') ? null : $text;
    }

    /**
     * Whether a remark is just the blank template's guidance text, left unchanged by the company.
     */
    public static function isGuidance(string $remarks, SurveyCycle $cycle): bool
    {
        return in_array(self::squash($remarks), array_map(fn (string $text) => self::squash($text), $cycle->lookup('_benefit_guidance')), true);
    }

    /**
     * Collapses whitespace (the template's lists and guidance contain line breaks and double spaces).
     */
    private static function squash(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
