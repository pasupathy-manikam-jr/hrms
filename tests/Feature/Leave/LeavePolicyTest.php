<?php

namespace Tests\Feature\Leave;

use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeavePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_filtered_by_leave_type_and_status()
    {
        $annual = LeaveType::factory()->create(['name' => 'Annual Leave']);
        LeavePolicy::factory()->for($annual)->create(['name' => 'Annual Policy']);
        LeavePolicy::factory()->create(['status' => 'inactive']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.leave-policies.index', ['leave_type_id' => $annual->id]))
            ->assertInertia(fn ($page) => $page
                ->component('hr/leave-policies/index')
                ->has('leavePolicies.data', 1)
                ->where('leavePolicies.data.0.name', 'Annual Policy')
                ->where('leavePolicies.data.0.leave_type.name', 'Annual Leave')
                ->where('statusCounts', ['all' => 1, 'active' => 1, 'inactive' => 0]));

        $this->get(route('hr.leave-policies.index', ['status' => 'inactive']))
            ->assertInertia(fn ($page) => $page
                ->has('leavePolicies.data', 1)
                ->where('statusCounts', ['all' => 2, 'active' => 1, 'inactive' => 1]));
    }

    public function test_policies_can_be_created_updated_and_deleted()
    {
        $type = LeaveType::factory()->create();
        $this->actingAs($this->userWithRole('hr'));

        $valid = [
            'name' => 'Annual Leave Policy', 'leave_type_id' => $type->id, 'accrual_type' => 'yearly', 'accrual_rate' => 21,
            'carry_forward_limit' => 5, 'min_days_per_application' => 1, 'max_days_per_application' => 15,
            'requires_approval' => true, 'status' => 'active',
        ];

        $this->post(route('hr.leave-policies.store'), ['name' => '', 'accrual_type' => 'weekly', 'leave_type_id' => 999])
            ->assertSessionHasErrors(['name', 'accrual_type', 'leave_type_id', 'status']);
        $this->post(route('hr.leave-policies.store'), [...$valid, 'min_days_per_application' => 5, 'max_days_per_application' => 2])
            ->assertSessionHasErrors('max_days_per_application');
        $this->post(route('hr.leave-policies.store'), $valid)->assertSessionHasNoErrors();

        $policy = LeavePolicy::where('name', 'Annual Leave Policy')->firstOrFail();
        $this->assertSame(15, $policy->max_days_per_application);

        $this->put(route('hr.leave-policies.update', $policy), [...$valid, 'requires_approval' => false])->assertSessionHasNoErrors();
        $this->assertFalse($policy->fresh()->requires_approval);

        $this->put(route('hr.leave-policies.toggle-status', $policy))->assertSessionHasNoErrors();
        $this->assertSame('inactive', $policy->fresh()->status);

        $this->delete(route('hr.leave-policies.destroy', $policy));
        $this->assertModelMissing($policy);
    }

    public function test_employees_can_view_but_not_manage_policies()
    {
        $policy = LeavePolicy::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.leave-policies.index'))->assertOk();
        $this->post(route('hr.leave-policies.store'), ['name' => 'X'])->assertForbidden();
        $this->put(route('hr.leave-policies.update', $policy), ['name' => 'X'])->assertForbidden();
        $this->put(route('hr.leave-policies.toggle-status', $policy))->assertForbidden();
        $this->delete(route('hr.leave-policies.destroy', $policy))->assertForbidden();
        $this->assertModelExists($policy);
    }
}
