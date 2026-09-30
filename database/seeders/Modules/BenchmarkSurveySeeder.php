<?php

namespace Database\Seeders\Modules;

use App\Models\Benchmark\BenchmarkJob;
use App\Models\Benchmark\SurveyCycle;
use App\Models\Benchmark\SurveyParticipant;
use App\Support\Benchmark\SurveyWorkbook;
use Illuminate\Database\Seeder;

/**
 * A demo survey cycle with fictional companies (deterministic numbers), so the analytics have real cuts.
 * The organisers' template and catalogue are not in the repo: the lists and a few generic titles come
 * from database/demo/benchmark.json, and a real cycle is created by uploading the blank template.
 */
class BenchmarkSurveySeeder extends Seeder
{
    /** Company => [industry, state, size band, ownership, pay factor]. */
    private const COMPANIES = [
        'Seri Mekar Manufacturing Sdn Bhd' => ['Manufacturing', 'Selangor', '200-499', 'Local / Malaysian-owned', 0.95],
        'Perak Precision Parts Sdn Bhd' => ['Manufacturing', 'Perak', '100-199', 'Local / Malaysian-owned', 0.85],
        'Penang Microtech Sdn Bhd' => ['Manufacturing', 'Penang', '500-999', 'MNC / Foreign-owned', 1.15],
        'Johor Polymer Industries Sdn Bhd' => ['Manufacturing', 'Johor', '200-499', 'Local / Malaysian-owned', 0.9],
        'Klang Valley Logistics Sdn Bhd' => ['Logistics & Transportation', 'Selangor', '500-999', 'MNC / Foreign-owned', 1.05],
        'Utara Freight Services Sdn Bhd' => ['Logistics & Transportation', 'Kedah', '100-199', 'Local / Malaysian-owned', 0.85],
        'Pelabuhan Supply Chain Sdn Bhd' => ['Logistics & Transportation', 'Johor', '200-499', 'Local / Malaysian-owned', 0.95],
        'Bukit Bintang Digital Sdn Bhd' => ['Technology / IT', 'Kuala Lumpur', '50-99', 'Local / Malaysian-owned', 1.1],
        'Cyberjaya Cloud Systems Sdn Bhd' => ['Technology / IT', 'Selangor', '200-499', 'MNC / Foreign-owned', 1.3],
        'Mutiara Software Sdn Bhd' => ['Technology / IT', 'Penang', '50-99', 'Local / Malaysian-owned', 1.0],
        'Borneo Tech Solutions Sdn Bhd' => ['Technology / IT', 'Sarawak', '50-99', 'Local / Malaysian-owned', 0.9],
        'Nusantara Retail Group Berhad' => ['Retail & Trade', 'Kuala Lumpur', '1,000+', 'Local / Malaysian-owned', 1.0],
    ];

    /** Base monthly median (RM) per demo job. */
    private const BASE_PAY = [
        'HR Executive' => 3800, 'HR Manager' => 8500, 'Accounts Executive' => 3900, 'Finance Manager' => 10500,
        'Sales Executive' => 3600, 'Marketing Executive' => 3900, 'Software Engineer' => 5500, 'IT Manager' => 11500,
        'Customer Service Executive' => 3000, 'Logistics Executive' => 3500, 'Quality Assurance Executive' => 3800,
        'Maintenance Technician' => 2600, 'Warehouse Assistant' => 2000, 'Process Engineer' => 5000,
    ];

    public function run(): void
    {
        /** @var array{lookups: array<string, list<string>>, jobs: list<array<string, string>>} $demo */
        $demo = json_decode((string) file_get_contents(database_path('demo/benchmark.json')), true);
        $cycle = SurveyCycle::firstOrCreate(['name' => 'Demo 2025/2026'], ['status' => 'open', 'min_companies' => 3, 'lookups' => $demo['lookups']]);

        foreach ($demo['jobs'] as $job) {
            BenchmarkJob::updateOrCreate(['survey_cycle_id' => $cycle->id, 'code' => $job['code']], $job);
        }

        $jobs = collect($demo['jobs'])->keyBy('title');
        mt_srand(2025);
        $state = $cycle->lookup('StateList');
        $families = $cycle->lookup('JobFamilyList');

        foreach (self::COMPANIES as $name => [$industry, $location, $band, $ownership, $factor]) {
            // Industry-specific roles plus the common corporate ones.
            $titles = match ($industry) {
                'Manufacturing' => ['Process Engineer', 'Quality Assurance Executive', 'Maintenance Technician', 'Warehouse Assistant'],
                'Logistics & Transportation' => ['Logistics Executive', 'Warehouse Assistant', 'Maintenance Technician', 'Customer Service Executive'],
                'Technology / IT' => ['Software Engineer', 'IT Manager', 'Customer Service Executive', 'Marketing Executive'],
                default => ['Sales Executive', 'Marketing Executive', 'Customer Service Executive', 'Warehouse Assistant'],
            };
            $titles = [...$titles, 'HR Executive', 'HR Manager', 'Accounts Executive', 'Finance Manager', 'Sales Executive'];

            $salaryRows = collect(array_unique($titles))->map(function (string $title) use ($jobs, $factor) {
                $job = $jobs[$title];
                $entry = $job['typical_level'] === 'Entry / Non-Executive';
                $headcount = $entry ? mt_rand(12, 80) : mt_rand(1, 8);
                $female = (int) round($headcount * mt_rand(15, 60) / 100);
                $median = round(self::BASE_PAY[$title] * $factor * mt_rand(90, 112) / 100, -1);
                $tenure = [(int) floor($headcount * 0.25), (int) floor($headcount * 0.3), (int) floor($headcount * 0.2)];

                return SurveyWorkbook::withTotals([
                    'job_code' => $job['code'], 'job_family' => $job['job_family'], 'job_title' => $title, 'own_title' => null,
                    'job_level' => $job['typical_level'], 'headcount' => $headcount, 'male' => $headcount - $female, 'female' => $female,
                    'tenure_under_1' => $tenure[0], 'tenure_1_2' => $tenure[1], 'tenure_3_4' => $tenure[2],
                    'tenure_5_plus' => $headcount - array_sum($tenure),
                    'experience_required' => $entry ? '<2 yrs' : (str_contains($title, 'Manager') ? '5-9 yrs' : '2-4 yrs'),
                    'shift_based' => $entry,
                    'min_salary' => $headcount > 1 ? round($median * 0.85, -1) : null,
                    'max_salary' => $headcount > 1 ? round($median * 1.2, -1) : null,
                    'median_salary' => $median,
                    'avg_male' => $headcount > $female ? round($median * mt_rand(99, 106) / 100, -1) : null,
                    'avg_female' => $female > 0 ? round($median * mt_rand(92, 101) / 100, -1) : null,
                    'guaranteed_bonus_months' => mt_rand(0, 2) ? 1.0 : 0.0,
                    'allowance_transport' => $entry ? 0.0 : (float) (mt_rand(0, 1) * 200),
                    'allowance_meal' => $entry ? 150.0 : 0.0,
                    'allowance_housing' => 0.0,
                    'allowance_shift' => $entry ? (float) mt_rand(150, 300) : 0.0,
                    'allowance_phone' => str_contains($title, 'Manager') || str_contains($title, 'Sales') ? 100.0 : 0.0,
                    'allowance_overtime' => $entry ? (float) mt_rand(300, 700) : 0.0,
                    'allowance_outstation' => str_contains($title, 'Sales') ? (float) mt_rand(200, 500) : 0.0,
                    'allowance_other' => 0.0,
                    'other_allowance_note' => null,
                ]);
            })->values()->all();

            $managerTier = fn (int $base) => (string) ($base + mt_rand(0, 2) * 2);
            $yes = fn (int $chance) => mt_rand(1, 100) <= $chance ? 'Yes' : 'No';
            $benefits = [
                ['item' => 'epf_employer_rate', 'same_for_all' => true, 'company_value' => '13'],
                ['item' => 'annual_leave_days', 'same_for_all' => false, 'exec_value' => (string) mt_rand(12, 16), 'manager_value' => $managerTier(18)],
                ['item' => 'medical_leave_days', 'same_for_all' => true, 'company_value' => (string) mt_rand(14, 22)],
                ['item' => 'maternity_leave_days', 'same_for_all' => true, 'company_value' => '98'],
                ['item' => 'paternity_leave_days', 'same_for_all' => true, 'company_value' => (string) (mt_rand(0, 3) ? 7 : 14)],
                ['item' => 'outpatient_coverage', 'same_for_all' => true, 'company_value' => 'Yes'],
                ['item' => 'outpatient_limit', 'same_for_all' => false, 'exec_value' => (string) (mt_rand(8, 15) * 100), 'manager_value' => (string) (mt_rand(20, 40) * 100)],
                ['item' => 'hospitalisation_coverage', 'same_for_all' => true, 'company_value' => 'Yes'],
                ['item' => 'dental_coverage', 'same_for_all' => true, 'company_value' => $yes(70)],
                ['item' => 'optical_coverage', 'same_for_all' => true, 'company_value' => $yes(55)],
                ['item' => 'group_life_insurance', 'same_for_all' => true, 'company_value' => $yes(75)],
                ['item' => 'personal_accident_insurance', 'same_for_all' => true, 'company_value' => $yes(80)],
                ['item' => 'variable_bonus_months', 'same_for_all' => true, 'company_value' => (string) (mt_rand(5, 30) / 10)],
                ['item' => 'kpi_incentive_scheme', 'same_for_all' => true, 'company_value' => $yes(60)],
                ['item' => 'profit_sharing_scheme', 'same_for_all' => true, 'company_value' => $yes(25)],
                ['item' => 'employee_share_scheme', 'same_for_all' => true, 'company_value' => $yes(35)],
                ['item' => 'target_bonus_percent', 'same_for_all' => true, 'company_value' => (string) mt_rand(5, 20)],
                ['item' => 'flexible_work', 'same_for_all' => true, 'company_value' => $yes(55)],
            ];

            $pick = fn () => $families[array_rand($families)];
            SurveyParticipant::record($cycle, [
                'participant' => [
                    'company_name' => $name, 'industry' => $industry, 'state' => in_array($location, $state, true) ? $location : $state[0],
                    'employee_band' => $band, 'ownership_type' => $ownership, 'revenue_band' => null, 'locations' => mt_rand(1, 6),
                    'listed_status' => str_contains($name, 'Berhad') ? 'Listed' : 'Private', 'unionised' => mt_rand(0, 3) ? 'No' : 'Yes',
                    'authorised_name' => 'HR Department', 'authorised_designation' => 'HR Manager', 'consent_date' => '2026-09-30',
                ],
                'salaryRows' => $salaryRows,
                'benefits' => array_map(fn (array $benefit) => $benefit + ['company_value' => null, 'exec_value' => null, 'manager_value' => null, 'remarks' => null], $benefits),
                'attrition' => [
                    'attrition_rate' => mt_rand(60, 220) / 10, 'new_hire_attrition_rate' => mt_rand(100, 350) / 10,
                    'retirements' => mt_rand(0, 3), 'involuntary_terminations' => mt_rand(0, 5), 'contract_non_renewals' => mt_rand(0, 4),
                    'hardest_to_hire_1' => $salaryRows[0]['job_family'], 'hardest_to_hire_2' => 'Information Technology', 'hardest_to_hire_3' => $pick(),
                    'time_to_fill_days' => mt_rand(25, 75), 'headcount_plan' => ['Growing', 'Stable', 'Stable', 'Reducing'][mt_rand(0, 3)],
                    'hardest_to_retain_1' => $salaryRows[0]['job_family'], 'hardest_to_retain_2' => 'Sales & Marketing', 'hardest_to_retain_3' => null,
                    'pay_reason_for_leaving' => ['Yes', 'Yes', 'No', 'Not sure'][mt_rand(0, 3)],
                    'retrenched' => false,
                ],
                'warnings' => [],
            ]);
        }
    }
}
