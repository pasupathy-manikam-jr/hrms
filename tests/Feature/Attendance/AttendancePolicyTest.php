<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendancePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendancePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_shows_stats_and_filters_by_status()
    {
        AttendancePolicy::factory()->create(['name' => 'Strict', 'late_arrival_grace' => 5, 'overtime_rate_per_hour' => 200]);
        AttendancePolicy::factory()->create(['name' => 'Flexible', 'late_arrival_grace' => 30, 'overtime_rate_per_hour' => 100, 'status' => 'inactive']);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.attendance-policies.index', ['status' => 'inactive']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/attendance-policies/index')
                ->has('attendancePolicies.data', 1)
                ->where('attendancePolicies.data.0.name', 'Flexible')
                ->where('stats.total', 2)
                ->where('stats.active', 1)
                ->where('stats.avg_late_grace', 18)
                ->where('statusCounts.inactive', 1));
    }

    public function test_policies_can_be_created_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.attendance-policies.store'), ['name' => '', 'late_arrival_grace' => -1, 'half_day_threshold' => 30, 'status' => 'bogus'])
            ->assertSessionHasErrors(['name', 'late_arrival_grace', 'early_departure_grace', 'half_day_threshold', 'overtime_rate_per_hour', 'status']);

        $payload = ['name' => 'Standard', 'late_arrival_grace' => 15, 'early_departure_grace' => 15, 'half_day_threshold' => 4, 'overtime_rate_per_hour' => 150, 'status' => 'active'];
        $this->post(route('hr.attendance-policies.store'), $payload)->assertSessionHasNoErrors();
        $policy = AttendancePolicy::where('name', 'Standard')->firstOrFail();

        $this->put(route('hr.attendance-policies.update', $policy), [...$payload, 'half_day_threshold' => 4.5])->assertSessionHasNoErrors();
        $this->assertSame(4.5, $policy->fresh()->half_day_threshold);

        $this->put(route('hr.attendance-policies.toggle-status', $policy))->assertSessionHasNoErrors();
        $this->assertSame('inactive', $policy->fresh()->status);

        $this->delete(route('hr.attendance-policies.destroy', $policy));
        $this->assertModelMissing($policy);
    }

    public function test_employees_can_view_but_not_change_policies()
    {
        $policy = AttendancePolicy::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.attendance-policies.index'))->assertOk();
        $this->post(route('hr.attendance-policies.store'), ['name' => 'X'])->assertForbidden();
        $this->put(route('hr.attendance-policies.update', $policy), ['name' => 'X'])->assertForbidden();
        $this->put(route('hr.attendance-policies.toggle-status', $policy))->assertForbidden();
        $this->delete(route('hr.attendance-policies.destroy', $policy))->assertForbidden();
        $this->assertModelExists($policy);
    }
}
