<?php

namespace Tests\Feature\Lifecycle;

use App\Models\Designation;
use App\Models\Employee;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_see_every_promotion_with_filters_and_status_counts()
    {
        $first = Promotion::factory()->create();
        Promotion::factory()->create(['status' => 'approved']);
        Promotion::factory()->create(['status' => 'rejected']);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.promotions.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/promotions/index')
                ->has('promotions.data', 3)
                ->has('employees', 3)
                ->where('statusCounts', ['all' => 3, 'pending' => 1, 'approved' => 1, 'rejected' => 1]));

        $this->get(route('hr.promotions.index', ['status' => 'approved']))
            ->assertInertia(fn ($page) => $page->has('promotions.data', 1)->where('promotions.data.0.status', 'approved'));

        $this->get(route('hr.promotions.index', ['employee_id' => $first->employee_id]))
            ->assertInertia(fn ($page) => $page->has('promotions.data', 1)->where('promotions.data.0.id', $first->id));
    }

    public function test_employees_only_see_promotions_about_themselves()
    {
        $user = $this->userWithRole('employee');
        $own = Promotion::factory()->create(['employee_id' => Employee::factory()->create(['user_id' => $user->id])->id]);
        Promotion::factory()->create();

        $this->actingAs($user)
            ->get(route('hr.promotions.index'))
            ->assertInertia(fn ($page) => $page
                ->has('promotions.data', 1)
                ->where('promotions.data.0.id', $own->id)
                ->where('employees', []));

        $this->post(route('hr.promotions.store'), [])->assertForbidden();
        $this->put(route('hr.promotions.change-status', $own), ['status' => 'approved'])->assertForbidden();
        $this->delete(route('hr.promotions.destroy', $own))->assertForbidden();
    }

    public function test_create_records_the_current_designation_and_validates()
    {
        $employee = Employee::factory()->create();
        $target = Designation::factory()->create();

        $this->actingAs($this->userWithRole('hr'))
            ->post(route('hr.promotions.store'), ['employee_id' => '', 'effective_date' => 'soon'])
            ->assertSessionHasErrors(['employee_id', 'designation_id', 'promotion_date', 'effective_date']);

        $this->post(route('hr.promotions.store'), [
            'employee_id' => $employee->id,
            'designation_id' => $target->id,
            'promotion_date' => '2030-01-01',
            'effective_date' => '2030-02-01',
            'salary_adjustment' => '1500.50',
            'reason' => 'Great work',
        ])->assertSessionHasNoErrors();

        $promotion = Promotion::query()->sole();
        $this->assertSame('pending', $promotion->status);
        $this->assertSame($employee->designation_id, $promotion->previous_designation_id);
        $this->assertNotSame($target->id, $employee->fresh()?->designation_id);
    }

    public function test_approving_moves_the_employee_to_the_new_designation()
    {
        $promotion = Promotion::factory()->create();

        $this->actingAs($this->userWithRole('company'))
            ->put(route('hr.promotions.change-status', $promotion), ['status' => 'approved'])
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $promotion->fresh()?->status);
        $this->assertSame($promotion->designation_id, $promotion->employee->fresh()?->designation_id);

        // Decided promotions can't be approved again or edited.
        $this->put(route('hr.promotions.change-status', $promotion), ['status' => 'rejected'])->assertForbidden();
        $this->put(route('hr.promotions.update', $promotion), [])->assertForbidden();
    }

    public function test_rejecting_leaves_the_employee_unchanged_and_delete_works()
    {
        $promotion = Promotion::factory()->create();
        $before = $promotion->employee->designation_id;

        $this->actingAs($this->userWithRole('hr'))->put(route('hr.promotions.change-status', $promotion), ['status' => 'rejected']);

        $this->assertSame('rejected', $promotion->fresh()?->status);
        $this->assertSame($before, $promotion->employee->fresh()?->designation_id);

        $this->delete(route('hr.promotions.destroy', $promotion));
        $this->assertModelMissing($promotion);
    }
}
