<?php

namespace Tests\Feature\Recruitment;

use App\Models\OfferTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_counts_statuses()
    {
        OfferTemplate::factory()->count(2)->create();
        OfferTemplate::factory()->create(['name' => 'Zeta Internship', 'status' => 'inactive']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.offer-templates.index', ['search' => 'Zeta']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/offer-templates/index')
                ->has('offerTemplates.data', 1)
                ->where('statusCounts', ['all' => 3, 'active' => 2, 'inactive' => 1]));
    }

    public function test_templates_can_be_created_updated_and_deleted_and_record_their_variables()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.recruitment.offer-templates.store'), ['name' => '', 'status' => 'x'])
            ->assertSessionHasErrors(['name', 'template_content', 'status']);
        $this->post(route('hr.recruitment.offer-templates.store'), [
            'name' => 'Standard', 'status' => 'active',
            'template_content' => 'Dear {{candidate_name}}, the {job_title} role pays {{ salary }}. Regards, {{candidate_name}}',
        ])->assertSessionHasNoErrors();

        $template = OfferTemplate::where('name', 'Standard')->firstOrFail();
        $this->assertSame(['candidate_name', 'job_title', 'salary'], $template->variables);

        $this->put(route('hr.recruitment.offer-templates.update', $template), ['name' => 'Standard v2', 'status' => 'inactive', 'template_content' => 'Hi {{candidate_name}}'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['candidate_name'], $template->fresh()->variables);

        $before = $template->fresh()->status;
        $this->put(route('hr.recruitment.offer-templates.toggle-status', $template))->assertSessionHasNoErrors();
        $this->assertNotSame($before, $template->fresh()->status);

        $this->delete(route('hr.recruitment.offer-templates.destroy', $template));
        $this->assertModelMissing($template);
    }

    public function test_employees_cannot_manage_offer_templates()
    {
        $template = OfferTemplate::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.recruitment.offer-templates.index'))->assertForbidden();
        $this->put(route('hr.recruitment.offer-templates.toggle-status', $template))->assertForbidden();
        $this->delete(route('hr.recruitment.offer-templates.destroy', $template))->assertForbidden();
        $this->assertModelExists($template);
    }

    public function test_show_page_has_the_content_and_placeholders()
    {
        $template = OfferTemplate::factory()->create(['template_content' => 'Dear {{candidate_name}}, welcome as {job_title}.']);

        $this->withoutVite()->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.offer-templates.show', $template))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/offer-templates/show')
                ->where('offerTemplate.id', $template->id)
                ->where('offerTemplate.variables', ['candidate_name', 'job_title']));
    }

    public function test_show_page_is_scoped_to_own_templates()
    {
        $this->userWithRole();
        $user = User::factory()->create()->givePermissionTo(['manage-offer-templates', 'manage-own-offer-templates', 'view-offer-templates']);
        $other = OfferTemplate::factory()->create();
        $this->withoutVite()->actingAs($user);

        $this->get(route('hr.recruitment.offer-templates.show', $other))->assertNotFound();
        $this->get(route('hr.recruitment.offer-templates.show', OfferTemplate::factory()->create(['created_by' => $user->id])))->assertOk();
    }
}
