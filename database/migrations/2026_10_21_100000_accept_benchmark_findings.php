<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each finding is kept with what it is about (a salary row, benefit, field) and its rule, so it can be shown on its
 * row and accepted. Existing submissions are converted from their messages, unchanged; repeated job title + level
 * rows are no longer findings, so those are dropped.
 */
return new class extends Migration
{
    /** Message pattern => rule, for the findings that can be accepted. */
    private const RULES = [
        '/Male \+ Female \(\d+\) must equal/' => 'gender_total',
        '/Tenure headcounts \(\d+\) must equal/' => 'tenure_total',
        '/Min Base Salary is above the Median/' => 'min_above_median',
        '/Max Base Salary is below the Median/' => 'max_below_median',
        '/Avg\. Male Base Salary is missing/' => 'avg_male',
        '/Avg\. Female Base Salary is missing/' => 'avg_female',
        '/Guaranteed bonus of .* looks high/' => 'bonus_high',
        '/is above 100%/' => 'over_100',
        '/ranked more than once/' => 'ranked_twice',
        '/Retrenchment is "Yes" but no driver/' => 'retrenchment_driver',
        '/is not in DD\/MM\/YYYY format/' => 'date_format',
    ];

    public function up(): void
    {
        Schema::table('survey_participants', function (Blueprint $table) {
            $table->json('issues')->nullable()->after('warnings');
            $table->json('accepted_issues')->nullable()->after('issues');
            $table->json('raw_answers')->nullable()->after('accepted_issues');
        });

        $benefits = [
            'EPF employer contribution rate (%)' => 'epf_employer_rate', 'Annual leave (days/year)' => 'annual_leave_days',
            'Medical/sick leave (days/year)' => 'medical_leave_days', 'Maternity leave (days)' => 'maternity_leave_days',
            'Paternity leave (days)' => 'paternity_leave_days', 'Outpatient medical coverage provided?' => 'outpatient_coverage',
            'Outpatient annual limit (RM)' => 'outpatient_limit', 'Hospitalisation / inpatient coverage provided?' => 'hospitalisation_coverage',
            'Hospitalisation annual/lifetime limit (RM)' => 'hospitalisation_limit', 'Dental coverage provided?' => 'dental_coverage',
            'Dental coverage amount (RM)' => 'dental_amount', 'Optical coverage provided?' => 'optical_coverage',
            'Optical coverage amount (RM)' => 'optical_amount', 'Group life insurance provided?' => 'group_life_insurance',
            'Personal accident insurance provided?' => 'personal_accident_insurance', 'Variable / performance bonus paid (months)' => 'variable_bonus_months',
            'KPI / sales / production incentive scheme?' => 'kpi_incentive_scheme', 'Profit-sharing / company-wide incentive scheme?' => 'profit_sharing_scheme',
            'Employee Share Scheme (ESS) / stock options?' => 'employee_share_scheme', 'Other incentive notes' => 'other_incentive_notes',
            'Target bonus % for 2027' => 'target_bonus_percent', 'Flexible / hybrid work arrangement?' => 'flexible_work',
            'Other notable benefit' => 'other_benefit',
        ];

        DB::table('survey_participants')->orderBy('id')->each(function (object $participant) use ($benefits) {
            $issues = [];
            foreach (['error' => $participant->errors, 'warning' => $participant->warnings] as $type => $messages) {
                foreach (json_decode((string) $messages, true) ?: [] as $message) {
                    if (str_contains($message, 'is listed more than once')) {
                        continue;
                    }
                    $rule = null;
                    foreach (self::RULES as $pattern => $candidate) {
                        if (preg_match($pattern, $message)) {
                            $rule = $candidate;
                            break;
                        }
                    }
                    $issues[] = ['type' => $type, 'message' => $message, 'target' => $this->target($message, $benefits), 'rule' => $rule];
                }
            }

            $open = fn (string $type) => array_column(array_filter($issues, fn (array $issue) => $issue['type'] === $type), 'message');
            DB::table('survey_participants')->where('id', $participant->id)->update([
                'issues' => json_encode($issues),
                'errors' => $open('error') === [] ? null : json_encode($open('error')),
                'warnings' => json_encode($open('warning')),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('survey_participants', function (Blueprint $table) {
            $table->dropColumn(['issues', 'accepted_issues', 'raw_answers']);
        });
    }

    /**
     * @param  array<string, string>  $benefits  label => item
     */
    private function target(string $message, array $benefits): ?string
    {
        return match (true) {
            (bool) preg_match('/^Salary Data row (\d+) /', $message, $row) => "row:{$row[1]}",
            str_starts_with($message, 'Salary Data ›') => 'salary',
            str_starts_with($message, 'Company Profile ›') => 'profile',
            str_starts_with($message, 'Consent & Submission ›') => 'consent',
            str_contains($message, '(hardest to hire)') => 'attrition:hardest_to_hire',
            str_contains($message, '(hardest to retain)') => 'attrition:hardest_to_retain',
            str_contains($message, 'Retrenchment is "Yes"') => 'attrition:retrenchment_driver',
            str_starts_with($message, 'Attrition & Hiring ›') => 'attrition',
            (bool) preg_match('/^Benefits › (.+?) ›/', $message, $item) => isset($benefits[$item[1]]) ? "benefit:{$benefits[$item[1]]}" : 'benefits',
            default => null,
        };
    }
};
