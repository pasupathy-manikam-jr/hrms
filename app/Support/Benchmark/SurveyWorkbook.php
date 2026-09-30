<?php

namespace App\Support\Benchmark;

use App\Models\Benchmark\SurveyCycle;
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
    private const ATTRITION_FIELDS = [
        3 => 'attrition_rate', 4 => 'new_hire_attrition_rate', 5 => 'retirements', 6 => 'involuntary_terminations',
        7 => 'contract_non_renewals', 8 => 'hardest_to_hire_1', 9 => 'hardest_to_hire_2', 10 => 'hardest_to_hire_3',
        11 => 'time_to_fill_days', 12 => 'headcount_plan', 13 => 'hardest_to_retain_1', 14 => 'hardest_to_retain_2',
        15 => 'hardest_to_retain_3', 16 => 'retention_initiatives', 17 => 'pay_reason_for_leaving',
        18 => 'other_leaving_reasons', 19 => 'retrenched', 20 => 'retrenchment_driver',
        21 => 'retrenchment_above_statutory', 22 => 'retrenchment_above_note', 23 => 'comments',
    ];

    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    /**
     * The blank template's dropdown lists, job catalogue and the Benefits sheet's guidance text
     * (used to recognise remarks the company left unchanged).
     *
     * @return array{lookups: array<string, list<string>>, jobs: list<array<string, string|null>>}
     */
    public static function readTemplate(string $path): array
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

        $benefits = $book->getSheetByNameOrThrow(self::BENEFITS);
        $lookups['_benefit_guidance'] = array_values(array_filter(array_map(
            fn (int $row) => self::text($benefits->getCell("F{$row}")->getValue()),
            array_keys(self::BENEFIT_ITEMS),
        )));

        return ['lookups' => $lookups, 'jobs' => $jobs];
    }

    /**
     * One company's completed workbook, checked against the cycle's lists and catalogue.
     *
     * @param  array<string, array<string, mixed>>  $catalogue  job title => catalogue entry (code, job_family, title)
     * @return array{participant: array<string, mixed>, salaryRows: list<array<string, mixed>>, benefits: list<array<string, mixed>>, attrition: array<string, mixed>, errors: list<string>, warnings: list<string>}
     */
    public function read(string $path, SurveyCycle $cycle, array $catalogue): array
    {
        $this->errors = [];
        $this->warnings = [];

        try {
            $book = self::load($path, [self::PROFILE, self::SALARY, self::BENEFITS, self::ATTRITION, self::CONSENT]);
            self::assertTemplate($book);
        } catch (Throwable $e) {
            return ['participant' => [], 'salaryRows' => [], 'benefits' => [], 'attrition' => [], 'errors' => [$e->getMessage()], 'warnings' => []];
        }

        $participant = $this->readProfile($book->getSheetByNameOrThrow(self::PROFILE), $cycle)
            + $this->readConsent($book->getSheetByNameOrThrow(self::CONSENT));

        return [
            'participant' => $participant,
            'salaryRows' => $this->readSalaries($book->getSheetByNameOrThrow(self::SALARY), $cycle, $catalogue),
            'benefits' => $this->readBenefits($book->getSheetByNameOrThrow(self::BENEFITS), $cycle),
            'attrition' => $this->readAttrition($book->getSheetByNameOrThrow(self::ATTRITION), $cycle),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readProfile(Worksheet $sheet, SurveyCycle $cycle): array
    {
        $value = fn (int $row) => self::text($sheet->getCell("B{$row}")->getValue());

        $profile = [
            'company_name' => $value(3),
            'industry' => $this->choice($value(4), $cycle->lookup('IndustryList'), 'Company Profile › Industry', required: true),
            'state' => $this->choice($value(5), $cycle->lookup('StateList'), 'Company Profile › State', required: true),
            'employee_band' => $this->choice($value(6), $cycle->lookup('EmployeeBandList'), 'Company Profile › Number of Employees', required: true),
            'revenue_band' => $this->choice($value(7), $cycle->lookup('RevenueBandList'), 'Company Profile › Annual Revenue Band'),
            'ownership_type' => $this->choice($value(8), $cycle->lookup('OwnershipTypeList'), 'Company Profile › Ownership Type'),
            'locations' => $this->integer($sheet->getCell('B9')->getValue(), 'Company Profile › Operating Locations'),
            'listed_status' => $this->choice($value(10), $cycle->lookup('ListedStatusList'), 'Company Profile › Listed Status'),
            'unionised' => $this->choice($value(11), $cycle->lookup('YesNo'), 'Company Profile › Unionised Workforce'),
        ];

        if ($profile['company_name'] === null) {
            $this->errors[] = __('Company Profile › Company Name is missing.');
        }

        return $profile;
    }

    /**
     * @return array<string, mixed>
     */
    private function readConsent(Worksheet $sheet): array
    {
        if (strcasecmp((string) self::text($sheet->getCell('B5')->getValue()), 'Yes') !== 0) {
            $this->errors[] = __('Consent & Submission › The company has not consented (must be "Yes").');
        }

        return [
            'authorised_name' => self::text($sheet->getCell('B6')->getValue()),
            'authorised_designation' => self::text($sheet->getCell('B7')->getValue()),
            'consent_date' => $this->date($sheet->getCell('B8')->getValue()),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $catalogue
     * @return list<array<string, mixed>>
     */
    private function readSalaries(Worksheet $sheet, SurveyCycle $cycle, array $catalogue): array
    {
        $rows = [];
        $seen = [];

        for ($row = self::SALARY_ROWS[0]; $row <= self::SALARY_ROWS[1]; $row++) {
            $cell = fn (string $column) => $sheet->getCell("{$column}{$row}")->getValue();
            $title = self::text($cell('C'));

            if ($title === null) {
                continue;
            }

            $at = __('Salary Data row :row (:title)', ['row' => $row - 5, 'title' => $title]);
            $job = $catalogue[mb_strtolower($title)] ?? null;
            if ($job === null) {
                $this->errors[] = __(':at: ":title" is not a Standard Job Title in the catalogue.', ['at' => $at, 'title' => $title]);
            }

            $level = $this->choice(self::text($cell('E')), $cycle->lookup('JobLevelList'), "{$at} › Job Level", required: true);
            $number = fn (string $column, string $label) => $this->number($cell($column), "{$at} › {$label}");
            $count = fn (string $column, string $label) => (int) ($this->number($cell($column), "{$at} › {$label}") ?? 0);

            $data = [
                'job_code' => $job['code'] ?? null,
                'job_family' => $job['job_family'] ?? '',
                'job_title' => $job['title'] ?? $title,
                'own_title' => self::text($cell('D')),
                'job_level' => $level ?? '',
                'headcount' => $count('F', 'Total Headcount'),
                'male' => $count('G', 'Male'),
                'female' => $count('H', 'Female'),
                'tenure_under_1' => $count('J', 'Tenure <1 yr'),
                'tenure_1_2' => $count('K', 'Tenure 1-2 yrs'),
                'tenure_3_4' => $count('L', 'Tenure 3-4 yrs'),
                'tenure_5_plus' => $count('M', 'Tenure 5+ yrs'),
                'experience_required' => $this->choice(self::text($cell('O')), $cycle->lookup('ExperienceRequiredList'), "{$at} › Typical Experience"),
                'shift_based' => ($shift = $this->choice(self::text($cell('P')), $cycle->lookup('YesNo'), "{$at} › Shift-Based")) === null ? null : $shift === 'Yes',
                'min_salary' => $number('Q', 'Min Base Salary'),
                'max_salary' => $number('R', 'Max Base Salary'),
                'median_salary' => $number('S', 'Median Base Salary'),
                'avg_male' => $number('T', 'Avg. Male Base Salary'),
                'avg_female' => $number('U', 'Avg. Female Base Salary'),
                'guaranteed_bonus_months' => $number('W', 'Guaranteed Annual Bonus'),
                'allowance_transport' => $number('X', 'Transport'),
                'allowance_meal' => $number('Y', 'Meal'),
                'allowance_housing' => $number('Z', 'Housing'),
                'allowance_shift' => $number('AA', 'Shift / Night Differential'),
                'allowance_phone' => $number('AB', 'Phone / Internet'),
                'allowance_overtime' => $number('AC', 'Overtime'),
                'allowance_outstation' => $number('AD', 'Outstation / Travel'),
                'allowance_other' => $number('AE', 'Other Allowance'),
                'other_allowance_note' => self::text($cell('AF')),
            ];

            $this->checkSalaryRow($data, $at);
            $key = mb_strtolower($data['job_title'].'|'.$data['job_level']);
            if (isset($seen[$key])) {
                $this->errors[] = __(':at: :title at :level is listed more than once.', ['at' => $at, 'title' => $data['job_title'], 'level' => $data['job_level']]);
            }
            $seen[$key] = true;
            $rows[] = self::withTotals($data);
        }

        if ($rows === []) {
            $this->errors[] = __('Salary Data › No job rows were filled in.');
        }

        return $rows;
    }

    /**
     * The workbook's own Check columns and sanity rules.
     *
     * @param  array<string, mixed>  $row
     */
    private function checkSalaryRow(array $row, string $at): void
    {
        if ($row['headcount'] < 1) {
            $this->errors[] = __(':at: Total Headcount must be at least 1.', ['at' => $at]);
        }
        if ($row['headcount'] !== $row['male'] + $row['female']) {
            $this->errors[] = __(':at: Male + Female (:sum) must equal Total Headcount (:total).', ['at' => $at, 'sum' => $row['male'] + $row['female'], 'total' => $row['headcount']]);
        }
        $tenure = $row['tenure_under_1'] + $row['tenure_1_2'] + $row['tenure_3_4'] + $row['tenure_5_plus'];
        if ($tenure !== $row['headcount']) {
            $this->errors[] = __(':at: Tenure headcounts (:sum) must equal Total Headcount (:total).', ['at' => $at, 'sum' => $tenure, 'total' => $row['headcount']]);
        }
        if ($row['median_salary'] === null || $row['median_salary'] <= 0) {
            $this->errors[] = __(':at: Median Base Salary is required.', ['at' => $at]);
        }
        if ($row['min_salary'] !== null && $row['median_salary'] !== null && $row['min_salary'] > $row['median_salary']) {
            $this->errors[] = __(':at: Min Base Salary is above the Median.', ['at' => $at]);
        }
        if ($row['max_salary'] !== null && $row['median_salary'] !== null && $row['max_salary'] < $row['median_salary']) {
            $this->errors[] = __(':at: Max Base Salary is below the Median.', ['at' => $at]);
        }
        if ($row['male'] > 0 && $row['avg_male'] === null) {
            $this->warnings[] = __(':at: Avg. Male Base Salary is missing although there are male employees.', ['at' => $at]);
        }
        if ($row['female'] > 0 && $row['avg_female'] === null) {
            $this->warnings[] = __(':at: Avg. Female Base Salary is missing although there are female employees.', ['at' => $at]);
        }
        if (($row['guaranteed_bonus_months'] ?? 0) > 6) {
            $this->warnings[] = __(':at: Guaranteed bonus of :months months looks high.', ['at' => $at, 'months' => $row['guaranteed_bonus_months']]);
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
        $guidance = array_map(fn (string $text) => self::squash($text), $cycle->lookup('_benefit_guidance'));
        $benefits = [];

        foreach (self::BENEFIT_ITEMS as $row => [$key, $label]) {
            $cell = fn (string $column) => self::text($sheet->getCell("{$column}{$row}")->getValue());
            $same = $this->choice($cell('B'), $cycle->lookup('YesNo'), "Benefits › {$label} › Same for all job levels?");
            $remarks = $cell('F');

            // Remarks left as the template's own guidance text aren't the company's answer.
            if ($remarks !== null && in_array(self::squash($remarks), $guidance, true)) {
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
            $answers[$field] = match ($field) {
                'attrition_rate', 'new_hire_attrition_rate' => $this->percent($value($row), "Attrition & Hiring › {$field}"),
                'retirements', 'involuntary_terminations', 'contract_non_renewals', 'time_to_fill_days' => $this->integer($value($row), "Attrition & Hiring › {$field}"),
                'hardest_to_hire_1', 'hardest_to_hire_2', 'hardest_to_hire_3', 'hardest_to_retain_1', 'hardest_to_retain_2', 'hardest_to_retain_3' => $this->choice(self::text($value($row)), $families, 'Attrition & Hiring › '.str_replace('_', ' ', $field)),
                'headcount_plan' => $this->choice(self::text($value($row)), $cycle->lookup('HeadcountPlanList'), 'Attrition & Hiring › Headcount plan'),
                'pay_reason_for_leaving' => $this->choice(self::text($value($row)), $cycle->lookup('YesNoUnsure'), 'Attrition & Hiring › Pay as a reason for leaving'),
                'retrenched' => ($retrenched = $this->choice(self::text($value($row)), $cycle->lookup('YesNo'), 'Attrition & Hiring › Retrenchment')) === null ? null : $retrenched === 'Yes',
                'retrenchment_driver' => $this->choice(self::text($value($row)), $cycle->lookup('RetrenchmentDriverList'), 'Attrition & Hiring › Retrenchment driver'),
                'retrenchment_above_statutory' => $this->choice(self::text($value($row)), $cycle->lookup('YesNoNA'), 'Attrition & Hiring › Retrenchment benefits above statutory'),
                default => self::text($value($row)),
            };
        }

        foreach (['hardest_to_hire', 'hardest_to_retain'] as $group) {
            $ranked = array_filter([$answers["{$group}_1"], $answers["{$group}_2"], $answers["{$group}_3"]]);
            if (count($ranked) !== count(array_unique($ranked))) {
                $this->warnings[] = __('Attrition & Hiring › The same job function is ranked more than once (:group).', ['group' => str_replace('_', ' ', $group)]);
            }
        }

        if ($answers['retrenched'] === true && $answers['retrenchment_driver'] === null) {
            $this->warnings[] = __('Attrition & Hiring › Retrenchment is "Yes" but no driver was given.');
        }

        return $answers;
    }

    /**
     * An answer that must be one of the template's dropdown values (case-insensitive); returns the list's spelling.
     *
     * @param  list<string>  $options
     */
    private function choice(?string $value, array $options, string $field, bool $required = false): ?string
    {
        if ($value === null) {
            if ($required) {
                $this->errors[] = __(':field is missing.', ['field' => $field]);
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

        $this->errors[] = __(':field: ":value" is not one of the allowed options.', ['field' => $field, 'value' => $value]);

        return null;
    }

    private function number(mixed $value, string $field): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace([',', 'RM', 'rm', ' '], '', $value);
        }

        if (! is_numeric($value)) {
            $this->errors[] = __(':field: ":value" is not a number.', ['field' => $field, 'value' => $value]);

            return null;
        }

        if ((float) $value < 0) {
            $this->errors[] = __(':field cannot be negative.', ['field' => $field]);

            return null;
        }

        return round((float) $value, 2);
    }

    private function integer(mixed $value, string $field): ?int
    {
        $number = $this->number($value, $field);

        return $number === null ? null : (int) round($number);
    }

    private function percent(mixed $value, string $field): ?float
    {
        $number = $this->number(is_string($value) ? rtrim($value, '%') : $value, $field);

        if ($number !== null && $number > 100) {
            $this->warnings[] = __(':field of :value% is above 100%.', ['field' => $field, 'value' => $number]);
        }

        return $number;
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return is_numeric($value)
                ? CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString()
                : CarbonImmutable::createFromFormat('d/m/Y', trim((string) $value))?->toDateString();
        } catch (Throwable) {
            $this->warnings[] = __('Consent & Submission › Date ":value" is not in DD/MM/YYYY format.', ['value' => $value]);

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
     * Collapses whitespace (the template's lists and guidance contain line breaks and double spaces).
     */
    private static function squash(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
