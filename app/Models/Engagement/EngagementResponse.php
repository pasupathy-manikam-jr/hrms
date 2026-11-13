<?php

namespace App\Models\Engagement;

use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * One employee's submitted survey. The org and demographic columns are copied from the employee at
 * submission so results don't shift when people later move department or have a birthday.
 *
 * @property int $id
 * @property int $engagement_campaign_id
 * @property int $employee_id
 */
#[Fillable(['engagement_campaign_id', 'employee_id', 'branch_id', 'department_id', 'designation_id', 'gender', 'age_band', 'tenure_band'])]
class EngagementResponse extends Model
{
    public const AGE_BANDS = ['<25', '25-34', '35-44', '45-54', '55+'];

    public const TENURE_BANDS = ['<1', '1-2', '3-5', '6-10', '10+'];

    /**
     * @return array<string, mixed>
     */
    public static function snapshotOf(Employee $employee): array
    {
        return [
            'employee_id' => $employee->id,
            'branch_id' => $employee->branch_id,
            'department_id' => $employee->department_id,
            'designation_id' => $employee->designation_id,
            'gender' => $employee->gender,
            'age_band' => self::band($employee->date_of_birth, [25, 35, 45, 55], self::AGE_BANDS),
            'tenure_band' => self::band($employee->date_of_joining, [1, 3, 6, 11], self::TENURE_BANDS),
        ];
    }

    /**
     * Validate a rating (1-4) for every statement of the campaign and save the response anonymously.
     */
    public static function submit(EngagementCampaign $campaign, Employee $employee, Request $request): self
    {
        $statementIds = $campaign->statements()->pluck('engagement_statements.id');
        $rules = $statementIds->mapWithKeys(fn ($id) => ["answers.{$id}" => ['required', 'integer', 'between:1,4']])->all();
        $answers = $request->validate(['answers' => ['required', 'array'], ...$rules], [
            'answers.*.required' => __('Please answer this statement.'),
        ])['answers'];

        return DB::transaction(function () use ($campaign, $employee, $statementIds, $answers) {
            $response = self::create([...self::snapshotOf($employee), 'engagement_campaign_id' => $campaign->id]);
            EngagementAnswer::insert($statementIds->map(fn ($id) => [
                'engagement_response_id' => $response->id,
                'engagement_statement_id' => $id,
                'rating' => (int) $answers[$id],
            ])->all());

            return $response;
        });
    }

    /**
     * @param  list<int>  $limits  upper bounds (exclusive) in whole years, one fewer than $bands
     * @param  list<string>  $bands
     */
    private static function band(?CarbonInterface $since, array $limits, array $bands): ?string
    {
        if ($since === null) {
            return null;
        }

        $years = (int) $since->diffInYears(now());

        foreach ($limits as $index => $limit) {
            if ($years < $limit) {
                return $bands[$index];
            }
        }

        return $bands[count($limits)];
    }

    /**
     * @return BelongsTo<EngagementCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EngagementCampaign::class, 'engagement_campaign_id');
    }

    /**
     * @return HasMany<EngagementAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(EngagementAnswer::class);
    }
}
