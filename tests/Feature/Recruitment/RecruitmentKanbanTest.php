<?php

namespace Tests\Feature\Recruitment;

use App\Models\Candidate;
use App\Models\Interview;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentKanbanTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_board_lists_every_filtered_candidate()
    {
        $user = $this->userWithRole();
        Candidate::factory()->count(12)->create();
        $zelda = Candidate::factory()->create(['first_name' => 'Zelda', 'status' => 'Offer']);

        $this->actingAs($user)
            ->get(route('hr.recruitment.candidates.kanban'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/candidates/kanban')
                ->has('candidates', 13) // not paginated
                ->has('jobPostings', 13)
                ->has('sources', 13)
                ->has('filters'));

        $this->get(route('hr.recruitment.candidates.kanban', ['search' => 'Zelda']))
            ->assertInertia(fn ($page) => $page
                ->has('candidates', 1)
                ->where('candidates.0.status', 'Offer')
                ->where('candidates.0.job.title', $zelda->job->title)
                ->where('filters.search', 'Zelda'));

        $this->get(route('hr.recruitment.candidates.kanban', ['job_id' => $zelda->job_id]))
            ->assertInertia(fn ($page) => $page->has('candidates', 1));
    }

    public function test_moving_a_candidate_card_goes_through_update_with_its_rules()
    {
        $this->userWithRole(); // seeds the permissions
        $recruiter = User::factory()->create()->givePermissionTo(['manage-candidates', 'manage-own-candidates', 'edit-candidates']);
        $mine = Candidate::factory()->create(['created_by' => $recruiter->id]);
        $other = Candidate::factory()->create();

        // Own scoping, like the list.
        $card = $this->actingAs($recruiter)
            ->get(route('hr.recruitment.candidates.kanban'))
            ->assertInertia(fn ($page) => $page->has('candidates', 1)->where('candidates.0.id', $mine->id))
            ->viewData('page')['props']['candidates'][0];

        // The board resends the card's own data with the new status.
        $this->put(route('hr.recruitment.candidates.update', $mine), [...$card, 'status' => 'Bogus'])->assertSessionHasErrors('status');
        $this->put(route('hr.recruitment.candidates.update', $mine), [...$card, 'status' => 'Screening'])->assertSessionHasNoErrors();
        $this->assertSame('Screening', $mine->fresh()->status);
        $this->assertSame($mine->email, $mine->fresh()->email);

        $this->put(route('hr.recruitment.candidates.update', $other), [...$card, 'status' => 'Hired'])->assertForbidden();

        $viewer = User::factory()->create()->givePermissionTo(['manage-candidates', 'manage-any-candidates']);
        $this->actingAs($viewer)->put(route('hr.recruitment.candidates.update', $mine), [...$card, 'status' => 'Hired'])->assertForbidden();
        $this->assertSame('Screening', $mine->fresh()->status);
    }

    public function test_interview_board_is_scoped_and_moves_through_update_status()
    {
        $employee = $this->userWithRole('employee');
        $mine = Interview::factory()->create();
        $mine->interviewers()->attach($employee);
        Interview::factory()->create(['status' => 'Completed']);

        $this->actingAs($employee)
            ->get(route('hr.recruitment.interviews.kanban'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/interviews/kanban')
                ->has('interviews', 1)
                ->where('interviews.0.id', $mine->id)
                ->where('interviews.0.interviewers.0.id', $employee->id));

        // Interviewers may see the board but not move cards.
        $this->put(route('hr.recruitment.interviews.update-status', $mine), ['status' => 'Completed'])->assertForbidden();

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.recruitment.interviews.kanban', ['candidate_id' => $mine->candidate_id]))
            ->assertInertia(fn ($page) => $page->has('interviews', 1)->has('candidates', 2));

        $this->put(route('hr.recruitment.interviews.update-status', $mine), ['status' => 'Bogus'])->assertSessionHasErrors('status');
        $this->put(route('hr.recruitment.interviews.update-status', $mine), ['status' => 'No-show'])->assertSessionHasNoErrors();
        $this->assertSame('No-show', $mine->fresh()->status);
    }

    public function test_offer_board_moves_keep_the_offer_side_effects_and_rules()
    {
        $offer = Offer::factory()->create(['status' => 'Sent', 'salary' => '72000.00']);
        $declined = Offer::factory()->create(['status' => 'Declined', 'decline_reason' => 'No']);
        $this->actingAs($this->userWithRole());

        $this->get(route('hr.recruitment.offers.kanban'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/offers/kanban')
                ->has('offers', 2)
                ->has('offers.0.candidate')
                ->has('candidates', 2));

        $this->put(route('hr.recruitment.offers.update-status', $offer), ['status' => 'Declined'])->assertSessionHasErrors('decline_reason');
        $this->put(route('hr.recruitment.offers.update-status', $declined), ['status' => 'Sent'])->assertForbidden();

        $this->put(route('hr.recruitment.offers.update-status', $offer), ['status' => 'Accepted'])->assertSessionHasNoErrors();
        $this->assertSame('Hired', $offer->candidate->fresh()->status);
    }

    public function test_employees_cannot_open_the_candidate_or_offer_boards()
    {
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.recruitment.candidates.kanban'))->assertForbidden();
        $this->get(route('hr.recruitment.offers.kanban'))->assertForbidden();
    }
}
