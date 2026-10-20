<?php

namespace App\Http\Controllers\Benchmark;

use App\Http\Controllers\Controller;
use App\Models\Benchmark\SurveyParticipant;
use App\Models\Benchmark\SurveySalaryRow;
use App\Support\Benchmark\SurveyWorkbook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Online corrections to a company's submission, section by section. Each save checks the input's types and
 * dropdown options, then re-checks the whole submission (see SurveyParticipant::corrected()).
 */
class SurveyCorrectionController extends Controller
{
    public function profile(Request $request, SurveyParticipant $participant): RedirectResponse
    {
        $cycle = $participant->cycle;
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', Rule::in($cycle->lookup('IndustryList'))],
            'state' => ['nullable', Rule::in($cycle->lookup('StateList'))],
            'employee_band' => ['nullable', Rule::in($cycle->lookup('EmployeeBandList'))],
            'revenue_band' => ['nullable', Rule::in($cycle->lookup('RevenueBandList'))],
            'ownership_type' => ['nullable', Rule::in($cycle->lookup('OwnershipTypeList'))],
            'locations' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'listed_status' => ['nullable', Rule::in($cycle->lookup('ListedStatusList'))],
            'unionised' => ['nullable', Rule::in($cycle->lookup('YesNo'))],
            'consented' => ['required', 'boolean'],
            'authorised_name' => ['nullable', 'string', 'max:255'],
            'authorised_designation' => ['nullable', 'string', 'max:255'],
            'consent_date' => ['nullable', 'date'],
        ]);

        // The company key is how a re-upload finds this submission, so it must stay unique in the cycle.
        $key = SurveyParticipant::keyFor($data['company_name']);
        if (SurveyParticipant::query()->where('survey_cycle_id', $cycle->id)->where('company_key', $key)->whereKeyNot($participant->id)->exists()) {
            return back()->withErrors(['company_name' => __('Another company in this cycle already has this name.')]);
        }

        $participant->update([...$data, 'company_key' => $key]);

        return $this->saved($request, $participant);
    }

    public function storeRow(Request $request, SurveyParticipant $participant): RedirectResponse
    {
        $participant->salaryRows()->create($this->salaryRow($request, $participant) + [
            'line' => (int) $participant->salaryRows()->max('line') + 1,
        ]);

        return $this->saved($request, $participant);
    }

    public function updateRow(Request $request, SurveyParticipant $participant, SurveySalaryRow $salaryRow): RedirectResponse
    {
        $salaryRow->update($this->salaryRow($request, $participant));

        return $this->saved($request, $participant);
    }

    public function destroyRow(Request $request, SurveyParticipant $participant, SurveySalaryRow $salaryRow): RedirectResponse
    {
        $salaryRow->delete();

        return $this->saved($request, $participant);
    }

    public function benefits(Request $request, SurveyParticipant $participant): RedirectResponse
    {
        $items = array_column(SurveyWorkbook::BENEFIT_ITEMS, 0);
        $data = $request->validate([
            'benefits' => ['present', 'array'],
            'benefits.*.item' => ['required', 'distinct', Rule::in($items)],
            'benefits.*.same_for_all' => ['nullable', 'boolean'],
            'benefits.*.company_value' => ['nullable', 'string', 'max:255'],
            'benefits.*.exec_value' => ['nullable', 'string', 'max:255'],
            'benefits.*.manager_value' => ['nullable', 'string', 'max:255'],
            'benefits.*.remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($participant, $data) {
            $participant->benefits()->delete();
            $participant->benefits()->createMany(array_filter(
                $data['benefits'],
                fn (array $answer) => collect($answer)->except('item')->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty(),
            ));
        });

        return $this->saved($request, $participant);
    }

    public function attrition(Request $request, SurveyParticipant $participant): RedirectResponse
    {
        $cycle = $participant->cycle;
        $families = Rule::in($cycle->lookup('JobFamilyList'));
        $rules = [];
        foreach (SurveyWorkbook::ATTRITION_FIELDS as $field) {
            $rules[$field] = match ($field) {
                'attrition_rate', 'new_hire_attrition_rate' => ['nullable', 'numeric', 'min:0', 'max:9999'],
                'retirements', 'involuntary_terminations', 'contract_non_renewals', 'time_to_fill_days' => ['nullable', 'integer', 'min:0'],
                'hardest_to_hire_1', 'hardest_to_hire_2', 'hardest_to_hire_3', 'hardest_to_retain_1', 'hardest_to_retain_2', 'hardest_to_retain_3' => ['nullable', $families],
                'headcount_plan' => ['nullable', Rule::in($cycle->lookup('HeadcountPlanList'))],
                'pay_reason_for_leaving' => ['nullable', Rule::in($cycle->lookup('YesNoUnsure'))],
                'retrenched' => ['nullable', 'boolean'],
                'retrenchment_driver' => ['nullable', Rule::in($cycle->lookup('RetrenchmentDriverList'))],
                'retrenchment_above_statutory' => ['nullable', Rule::in($cycle->lookup('YesNoNA'))],
                default => ['nullable', 'string', 'max:5000'],
            };
        }

        $participant->attrition()->updateOrCreate([], $request->validate($rules));

        return $this->saved($request, $participant);
    }

    /**
     * A salary row's answers, with the catalogue's spelling, code and family and the recalculated totals.
     *
     * @return array<string, mixed>
     */
    private function salaryRow(Request $request, SurveyParticipant $participant): array
    {
        $cycle = $participant->cycle;
        $count = ['required', 'integer', 'min:0', 'max:1000000'];
        $money = ['nullable', 'numeric', 'min:0', 'max:9999999999'];
        $data = $request->validate([
            'job_title' => ['required', 'string', 'max:255'],
            'own_title' => ['nullable', 'string', 'max:255'],
            'job_level' => ['nullable', Rule::in($cycle->lookup('JobLevelList'))],
            'headcount' => $count, 'male' => $count, 'female' => $count,
            'tenure_under_1' => $count, 'tenure_1_2' => $count, 'tenure_3_4' => $count, 'tenure_5_plus' => $count,
            'experience_required' => ['nullable', Rule::in($cycle->lookup('ExperienceRequiredList'))],
            'shift_based' => ['nullable', 'boolean'],
            'min_salary' => $money, 'max_salary' => $money, 'median_salary' => $money,
            'avg_male' => $money, 'avg_female' => $money,
            'guaranteed_bonus_months' => ['nullable', 'numeric', 'min:0', 'max:999'],
            ...array_fill_keys(array_map(fn (string $type) => "allowance_{$type}", SurveySalaryRow::ALLOWANCES), ['nullable', 'numeric', 'min:0', 'max:99999999']),
            'other_allowance_note' => ['nullable', 'string', 'max:255'],
        ]);

        // A title outside the catalogue is saved as typed and flagged by the re-check.
        $job = $cycle->catalogue()[mb_strtolower($data['job_title'])] ?? null;

        return SurveyWorkbook::withTotals([
            ...$data,
            'job_level' => $data['job_level'] ?? '',
            'job_title' => $job['title'] ?? $data['job_title'],
            'job_code' => $job['code'] ?? null,
            'job_family' => $job['job_family'] ?? '',
        ]);
    }

    private function saved(Request $request, SurveyParticipant $participant): RedirectResponse
    {
        $participant->corrected($request->user()?->id);
        $errors = count($participant->errors ?? []);

        return $this->toast($errors === 0 ? 'success' : 'warning', $errors === 0
            ? __('Saved. The submission has no errors left and now counts in the analytics.')
            : __('Saved. :n errors left to fix.', ['n' => $errors]));
    }
}
