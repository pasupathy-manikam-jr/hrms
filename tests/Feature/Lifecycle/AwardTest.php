<?php

namespace Tests\Feature\Lifecycle;

use App\Models\Award;
use App\Models\AwardType;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwardTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_see_every_award_and_can_filter()
    {
        $first = Award::factory()->create();
        Award::factory()->create();

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.awards.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/awards/index')
                ->has('awards.data', 2)
                ->has('awardTypes', 2)
                ->has('employees', 2));

        $this->get(route('hr.awards.index', ['award_type_id' => $first->award_type_id]))
            ->assertInertia(fn ($page) => $page->has('awards.data', 1)->where('awards.data.0.id', $first->id));
    }

    public function test_employees_only_see_their_own_awards_and_cannot_manage_them()
    {
        $user = $this->userWithRole('employee');
        $own = Award::factory()->create(['employee_id' => Employee::factory()->create(['user_id' => $user->id])->id]);
        Award::factory()->create();

        $this->actingAs($user)
            ->get(route('hr.awards.index'))
            ->assertInertia(fn ($page) => $page->has('awards.data', 1)->where('awards.data.0.id', $own->id)->where('employees', []));

        $this->post(route('hr.awards.store'), [])->assertForbidden();
        $this->put(route('hr.awards.update', $own), [])->assertForbidden();
        $this->delete(route('hr.awards.destroy', $own))->assertForbidden();
    }

    public function test_create_update_and_delete()
    {
        $employee = Employee::factory()->create();
        $type = AwardType::factory()->create();

        $this->actingAs($this->userWithRole('company'))
            ->post(route('hr.awards.store'), ['monetary_value' => -1])
            ->assertSessionHasErrors(['employee_id', 'award_type_id', 'award_date', 'monetary_value']);

        $payload = ['employee_id' => $employee->id, 'award_type_id' => $type->id, 'award_date' => '2030-01-01', 'gift' => 'Trophy', 'monetary_value' => '250'];
        $this->post(route('hr.awards.store'), $payload)->assertSessionHasNoErrors();

        $award = Award::query()->sole();
        $this->assertSame('Trophy', $award->gift);

        $this->put(route('hr.awards.update', $award), [...$payload, 'gift' => 'Plaque'])->assertSessionHasNoErrors();
        $this->assertSame('Plaque', $award->fresh()?->gift);

        $this->delete(route('hr.awards.destroy', $award));
        $this->assertModelMissing($award);
    }

    public function test_award_page_shows_the_award_and_recipient()
    {
        $this->withoutVite();
        $award = Award::factory()->create(['gift' => 'Trophy', 'monetary_value' => 500]);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.awards.show', $award))
            ->assertInertia(fn ($page) => $page
                ->component('hr/awards/show')
                ->where('award.id', $award->id)
                ->where('award.gift', 'Trophy')
                ->where('award.monetary_value', '500.00')
                ->where('award.employee.user.name', $award->employee->user->name)
                ->has('award.award_type.name'));
    }

    public function test_employees_can_only_open_their_own_awards()
    {
        $this->withoutVite();
        $user = $this->userWithRole('employee');
        $own = Award::factory()->create(['employee_id' => Employee::factory()->create(['user_id' => $user->id])->id]);
        $other = Award::factory()->create();

        $this->actingAs($user)->get(route('hr.awards.show', $own))->assertOk();
        $this->get(route('hr.awards.show', $other))->assertNotFound();
    }
}
