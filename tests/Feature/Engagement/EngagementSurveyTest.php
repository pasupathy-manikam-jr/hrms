<?php

namespace Tests\Feature\Engagement;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Engagement\EngagementAnswer;
use App\Models\Engagement\EngagementCampaign;
use App\Models\Engagement\EngagementElement;
use App\Models\Engagement\EngagementResponse;
use App\Models\Engagement\EngagementStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngagementSurveyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migration_seeds_the_default_bank_and_tiers()
    {
        $this->assertSame(8, EngagementElement::count());
        $this->assertSame(67, EngagementStatement::count());
        $this->assertSame(8, EngagementStatement::where('reverse_scored', true)->count());
        $this->assertDatabaseCount('engagement_tiers', 5);
    }

    public function test_statements_can_be_listed_created_toggled_and_deleted_only_while_unused()
    {
        $this->actingAs($this->userWithRole());
        $element = EngagementElement::firstOrFail();

        $this->get(route('engagement.statements.index', ['element' => $element->id]))
            ->assertInertia(fn ($page) => $page->component('engagement/statements/index')->has('statements.data', 8)->has('tiers', 5));

        $this->post(route('engagement.statements.store'), ['text' => ''])->assertSessionHasErrors('text');
        $this->post(route('engagement.statements.store'), ['text' => 'I like Mondays', 'engagement_element_id' => $element->id, 'reverse_scored' => true])
            ->assertSessionHasNoErrors();
        $statement = EngagementStatement::where('text', 'I like Mondays')->firstOrFail();
        $this->assertTrue($statement->reverse_scored);

        $this->put(route('engagement.statements.toggle-status', $statement));
        $this->assertFalse($statement->fresh()->is_active);

        // A statement a campaign asks can't be deleted.
        $used = EngagementStatement::where('is_active', true)->firstOrFail();
        $this->post(route('engagement.campaigns.store'), ['name' => 'Q1', 'status' => 'draft', 'min_group_size' => 5]);
        $this->delete(route('engagement.statements.destroy', $used));
        $this->assertModelExists($used);

        $this->delete(route('engagement.statements.destroy', $statement));
        $this->assertModelMissing($statement);
    }

    public function test_a_campaign_asks_every_active_statement()
    {
        $this->actingAs($this->userWithRole());
        EngagementStatement::query()->limit(7)->update(['is_active' => false]);

        $this->post(route('engagement.campaigns.store'), ['name' => '', 'status' => 'bogus', 'min_group_size' => 1])
            ->assertSessionHasErrors(['name', 'status', 'min_group_size']);
        $this->post(route('engagement.campaigns.store'), ['name' => '2026 survey', 'status' => 'open', 'min_group_size' => 5])
            ->assertSessionHasNoErrors();

        $campaign = EngagementCampaign::where('name', '2026 survey')->firstOrFail();
        $this->assertSame(60, $campaign->statements()->count());

        $this->put(route('engagement.campaigns.update', $campaign), ['name' => '2026 survey', 'status' => 'closed', 'min_group_size' => 6])
            ->assertSessionHasNoErrors();
        $this->assertSame('closed', $campaign->fresh()->status);

        $this->get(route('engagement.campaigns.index'))
            ->assertInertia(fn ($page) => $page->component('engagement/campaigns/index')->where('campaigns.data.0.statements_count', 60));
    }

    public function test_an_employee_answers_an_open_campaign_once()
    {
        $campaign = $this->openCampaign();
        $user = $this->employeeUser();

        $this->actingAs($user)->get(route('engagement.my.index'))
            ->assertInertia(fn ($page) => $page->component('engagement/my/index')->where('campaigns.0.can_answer', true));
        $this->get(route('engagement.my.show', $campaign))
            ->assertInertia(fn ($page) => $page->component('engagement/my/show')->has('statements', 67)->has('ratings', 4));

        // Every statement must be answered on the 1-4 scale.
        $ids = $campaign->statements()->pluck('engagement_statements.id');
        $partial = $ids->take(66)->mapWithKeys(fn ($id) => [$id => 3])->all();
        $this->post(route('engagement.my.store', $campaign), ['answers' => [...$partial, $ids->last() => 9]])
            ->assertSessionHasErrors("answers.{$ids->last()}");
        $this->assertDatabaseCount('engagement_responses', 0);

        $this->post(route('engagement.my.store', $campaign), ['answers' => $ids->mapWithKeys(fn ($id) => [$id => 4])->all()])
            ->assertRedirect(route('engagement.my.index'));

        $response = EngagementResponse::sole();
        $this->assertSame($user->employee->department_id, $response->department_id);
        $this->assertSame(67, $response->answers()->count());

        $this->post(route('engagement.my.store', $campaign), ['answers' => $ids->mapWithKeys(fn ($id) => [$id => 1])->all()]);
        $this->assertSame(1, EngagementResponse::count());
    }

    public function test_closed_campaigns_and_non_employees_cannot_be_answered()
    {
        $campaign = $this->openCampaign();
        $campaign->update(['status' => 'closed']);
        $ids = $campaign->statements()->pluck('engagement_statements.id');

        $this->actingAs($this->employeeUser())
            ->post(route('engagement.my.store', $campaign), ['answers' => $ids->mapWithKeys(fn ($id) => [$id => 4])->all()]);
        $this->assertDatabaseCount('engagement_responses', 0);

        $campaign->update(['status' => 'open']);
        $this->actingAs($this->userWithRole('hr'))->get(route('engagement.my.show', $campaign))->assertForbidden();
    }

    public function test_results_score_favourable_answers_with_reverse_scoring_and_hide_small_groups()
    {
        $campaign = $this->openCampaign();
        $sales = Department::factory()->create(['name' => 'Sales']);
        $support = Department::factory()->create(['name' => 'Support']);

        // 5 in Sales agree with everything; 2 in Support disagree with everything.
        foreach ([[$sales, 4], [$sales, 4], [$sales, 4], [$sales, 4], [$sales, 4], [$support, 1], [$support, 1]] as [$department, $rating]) {
            $this->respond($campaign, Employee::factory()->create(['department_id' => $department->id]), $rating);
        }

        $this->actingAs($this->userWithRole())
            ->get(route('engagement.campaigns.show', [$campaign, 'by' => 'department']))
            ->assertInertia(fn ($page) => $page
                ->component('engagement/campaigns/show')
                ->where('summary.respondents', 7)
                ->where('summary.suppressed', false)
                // Agreeing with a reverse-scored statement is unfavourable: Sales scores 59/67 per person,
                // Support (disagreeing with everything) scores 8/67; overall = (5×59 + 2×8) / (7×67).
                ->where('summary.overall', round((5 * 59 + 2 * 8) / (7 * 67), 2))
                ->where('summary.tiers.0.count', 0)
                ->where('breakdown.rows', fn ($rows) => count($rows) === 1 && $rows[0]['label'] === 'Sales' && $rows[0]['respondents'] === 5)
                ->where('breakdown.hidden', 1)
                ->missing('summary.respondent_ids'));
    }

    public function test_results_stay_hidden_until_the_minimum_group_has_answered()
    {
        $campaign = $this->openCampaign();
        $this->respond($campaign, Employee::factory()->create(), 4);

        $this->actingAs($this->userWithRole())
            ->get(route('engagement.campaigns.show', $campaign))
            ->assertInertia(fn ($page) => $page
                ->where('summary.suppressed', true)
                ->where('summary.overall', null)
                ->where('breakdown', null));
    }

    public function test_employees_cannot_manage_campaigns_or_see_results()
    {
        $campaign = $this->openCampaign();
        $this->actingAs($this->employeeUser());

        $this->get(route('engagement.campaigns.index'))->assertForbidden();
        $this->get(route('engagement.campaigns.show', $campaign))->assertForbidden();
        $this->get(route('engagement.statements.index'))->assertForbidden();
        $this->post(route('engagement.campaigns.store'), ['name' => 'X', 'status' => 'open', 'min_group_size' => 5])->assertForbidden();
        $this->delete(route('engagement.campaigns.destroy', $campaign))->assertForbidden();
        $this->assertModelExists($campaign);
    }

    private function openCampaign(): EngagementCampaign
    {
        $campaign = EngagementCampaign::create(['name' => 'Pulse', 'status' => 'open', 'min_group_size' => 5]);
        $campaign->attachActiveStatements();

        return $campaign;
    }

    private function employeeUser(): User
    {
        $user = $this->userWithRole('employee');
        Employee::factory()->create(['user_id' => $user->id]);

        return $user->fresh();
    }

    private function respond(EngagementCampaign $campaign, Employee $employee, int $rating): void
    {
        $response = EngagementResponse::create([...EngagementResponse::snapshotOf($employee), 'engagement_campaign_id' => $campaign->id]);

        EngagementAnswer::insert($campaign->statements()->pluck('engagement_statements.id')->map(fn ($id) => [
            'engagement_response_id' => $response->id,
            'engagement_statement_id' => $id,
            'rating' => $rating,
        ])->all());
    }
}
