<?php

namespace Database\Seeders\Modules;

use App\Models\Employee;
use App\Models\Engagement\EngagementAnswer;
use App\Models\Engagement\EngagementCampaign;
use App\Models\Engagement\EngagementElement;
use App\Models\Engagement\EngagementResponse;
use App\Models\Engagement\EngagementStatement;
use App\Models\Engagement\EngagementTier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * The default question bank and tiers (database/demo/engagement.json, original wording), plus a closed
 * demo campaign answered by the seeded employees so the results page has data.
 */
class EngagementSurveySeeder extends Seeder
{
    public function run(): void
    {
        self::seedDefaults();

        $employees = Employee::query()->where('employee_status', '!=', 'terminated')->get();

        if ($employees->isEmpty() || EngagementCampaign::query()->where('name', 'Engagement Survey 2026')->exists()) {
            return;
        }

        $campaign = EngagementCampaign::create(['name' => 'Engagement Survey 2026', 'status' => 'closed', 'closes_on' => now()->subMonth()->toDateString(), 'min_group_size' => 5]);
        $campaign->syncStatements();
        $statements = $campaign->statements()->get(['engagement_statements.id', 'engagement_element_id', 'reverse_scored']);

        mt_srand(2026);
        // Each department leans a little differently, and each element shifts the odds, so the cuts differ.
        $elementBias = $statements->pluck('engagement_element_id')->unique()->mapWithKeys(fn ($id) => [(int) $id => mt_rand(-15, 15)]);

        foreach ($employees as $employee) {
            $mood = 55 + ($employee->department_id % 5) * 6 + mt_rand(-20, 20);
            $response = EngagementResponse::create([...EngagementResponse::snapshotOf($employee), 'engagement_campaign_id' => $campaign->id]);

            EngagementAnswer::insert($statements->map(function (EngagementStatement $statement) use ($response, $mood, $elementBias) {
                $favourable = mt_rand(1, 100) <= $mood + ($elementBias[(int) $statement->engagement_element_id] ?? 0);
                $rating = $favourable ? mt_rand(3, 4) : mt_rand(1, 2);

                return [
                    'engagement_response_id' => $response->id,
                    'engagement_statement_id' => $statement->id,
                    'rating' => $statement->reverse_scored ? 5 - $rating : $rating,
                ];
            })->all());
        }
    }

    /**
     * Elements, statements and tiers; safe to run again (also run by the migration for live sites).
     */
    public static function seedDefaults(): void
    {
        /** @var array{elements: list<array{name: string, description: string, statements: list<array{0: string, 1: bool}>}>, overall: list<array{0: string, 1: bool}>, tiers: list<array<string, mixed>>} $bank */
        $bank = File::json(database_path('demo/engagement.json'), JSON_THROW_ON_ERROR);

        foreach ($bank['elements'] as $index => $data) {
            $element = EngagementElement::firstOrCreate(['name' => $data['name']], ['description' => $data['description'], 'sort_order' => $index + 1]);
            self::statements($data['statements'], $element->id);
        }

        self::statements($bank['overall'], null);

        foreach ($bank['tiers'] as $tier) {
            EngagementTier::updateOrCreate(['level' => $tier['level']], $tier);
        }
    }

    /**
     * @param  list<array{0: string, 1: bool}>  $statements
     */
    private static function statements(array $statements, ?int $elementId): void
    {
        foreach ($statements as $index => [$text, $reverse]) {
            EngagementStatement::firstOrCreate(
                ['text' => $text],
                ['engagement_element_id' => $elementId, 'reverse_scored' => $reverse, 'sort_order' => $index + 1],
            );
        }
    }
}
