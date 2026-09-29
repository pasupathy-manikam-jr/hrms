<?php

namespace Tests\Feature\Recruitment;

use App\Models\Candidate;
use App\Models\Offer;
use App\Models\OfferTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(Candidate $candidate, array $overrides = []): array
    {
        return $overrides + [
            'candidate_id' => $candidate->id,
            'offer_date' => '2026-10-01',
            'salary' => '85000.50',
            'start_date' => '2026-11-01',
            'expiration_date' => '2026-10-15',
        ];
    }

    public function test_list_can_be_filtered_and_counts_statuses()
    {
        Offer::factory()->count(2)->create();
        Offer::factory()->create(['status' => 'Sent', 'candidate_id' => Candidate::factory()->create(['first_name' => 'Zelda'])]);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.offers.index', ['search' => 'Zelda']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/offers/index')
                ->has('offers.data', 1)
                ->where('offers.data.0.status', 'Sent')
                ->where('statusCounts.all', 3)
                ->where('statusCounts.Draft', 2));
    }

    public function test_offers_are_created_as_drafts_updated_and_deleted()
    {
        $candidate = Candidate::factory()->create();
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.recruitment.offers.store'), ['salary' => -1, 'expiration_date' => '2020-01-01', 'offer_date' => '2026-01-01'])
            ->assertSessionHasErrors(['candidate_id', 'salary', 'start_date', 'expiration_date']);
        $this->post(route('hr.recruitment.offers.store'), $this->payload($candidate, ['status' => 'Accepted']))->assertSessionHasNoErrors();

        $offer = Offer::sole();
        $this->assertSame('Draft', $offer->status);
        $this->assertSame('85000.50', $offer->salary);
        $this->assertSame($candidate->job_id, $offer->job_id);

        $this->put(route('hr.recruitment.offers.update', $offer), $this->payload($candidate, ['salary' => '90000']))->assertSessionHasNoErrors();
        $this->assertSame('90000.00', $offer->fresh()->salary);

        $this->delete(route('hr.recruitment.offers.destroy', $offer));
        $this->assertModelMissing($offer);
    }

    public function test_accepting_hires_the_candidate_and_closes_the_offer()
    {
        $offer = Offer::factory()->create(['status' => 'Sent', 'salary' => '72000.00']);
        $user = $this->userWithRole();
        $this->actingAs($user);

        $this->put(route('hr.recruitment.offers.update-status', $offer), ['status' => 'Accepted'])->assertSessionHasNoErrors();

        $offer->refresh();
        $this->assertSame('Accepted', $offer->status);
        $this->assertNotNull($offer->response_date);
        $this->assertSame($user->id, $offer->approved_by);
        $this->assertSame('Hired', $offer->candidate->status);
        $this->assertEquals(72000, $offer->candidate->final_salary);

        $this->put(route('hr.recruitment.offers.update-status', $offer), ['status' => 'Draft'])->assertForbidden();
        $this->put(route('hr.recruitment.offers.update', $offer), $this->payload($offer->candidate))->assertForbidden();
    }

    public function test_declining_needs_a_reason_and_rejects_the_candidate()
    {
        $offer = Offer::factory()->create(['status' => 'Sent']);
        $this->actingAs($this->userWithRole());

        $this->put(route('hr.recruitment.offers.update-status', $offer), ['status' => 'Declined'])->assertSessionHasErrors('decline_reason');
        $this->put(route('hr.recruitment.offers.update-status', $offer), ['status' => 'Declined', 'decline_reason' => 'Counter offer'])->assertSessionHasNoErrors();

        $this->assertSame('Counter offer', $offer->fresh()->decline_reason);
        $this->assertSame('Rejected', $offer->candidate->fresh()->status);
    }

    public function test_show_page_fills_the_offer_letter_placeholders()
    {
        $template = OfferTemplate::factory()->create(['template_content' => 'Dear {{candidate_name}}, {job_title} at {{salary}} from {{start_date}}. {{unknown}}']);
        $offer = Offer::factory()->create([
            'offer_template_id' => $template->id,
            'salary' => '1234567.5',
            'start_date' => '2026-11-02',
            'candidate_id' => Candidate::factory()->create(['first_name' => 'Ada', 'last_name' => '<b>Lovelace</b>']),
        ]);

        $this->withoutVite()->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.offers.show', $offer))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/offers/show')
                ->where('offer.id', $offer->id)
                ->has('offer.job.title')
                // No currencies seeded, so the symbol falls back to the code; values are HTML-escaped.
                ->where('letter', "Dear Ada &lt;b&gt;Lovelace&lt;/b&gt;, {$offer->job->title} at MYR 1,234,567.50 from 02/11/2026. {{unknown}}"));

        $this->get(route('hr.recruitment.offers.show', Offer::factory()->create()))
            ->assertInertia(fn ($page) => $page->where('letter', null));
    }

    public function test_show_page_is_scoped_to_own_offers()
    {
        $this->userWithRole();
        $user = User::factory()->create()->givePermissionTo(['manage-offers', 'manage-own-offers', 'view-offers']);
        $other = Offer::factory()->create();
        $this->withoutVite()->actingAs($user);

        $this->get(route('hr.recruitment.offers.show', $other))->assertNotFound();
        $this->get(route('hr.recruitment.offers.show', Offer::factory()->create(['created_by' => $user->id])))->assertOk();
    }

    public function test_employees_cannot_access_offers()
    {
        $offer = Offer::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.recruitment.offers.index'))->assertForbidden();
        $this->put(route('hr.recruitment.offers.update-status', $offer), ['status' => 'Accepted'])->assertForbidden();
        $this->get(route('hr.recruitment.offers.show', $offer))->assertForbidden();
    }

    public function test_letter_fills_manager_hr_location_and_deadline_placeholders()
    {
        $hr = $this->userWithRole();
        $manager = User::factory()->create(['name' => 'Siti Hajar']);
        $template = OfferTemplate::factory()->create(['template_content' => '{{manager_name}}|{{hr_manager_name}}|{{work_location}}|{{acceptance_deadline}}']);
        $offer = Offer::factory()->create([
            'offer_template_id' => $template->id,
            'expiration_date' => '2026-12-01',
            'approved_by' => $manager->id,
            'created_by' => $hr->id,
        ]);

        $this->withoutVite()->actingAs($hr)
            ->get(route('hr.recruitment.offers.show', $offer))
            ->assertInertia(fn ($page) => $page->where('letter', "Siti Hajar|{$hr->name}|{$offer->job->location->name}|01/12/2026"));
    }
}
