<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_nests_people_by_who_they_report_to()
    {
        $company = $this->userWithRole('company');
        $manager = User::factory()->create(['name' => 'Mona Manager', 'reports_to_id' => $company->id])->assignRole('manager');
        $developer = User::factory()->create(['name' => 'Dev One', 'reports_to_id' => $manager->id])->assignRole('employee');

        $this->actingAs($company)->get(route('hr.organization-chart.index'))->assertInertia(fn ($page) => $page
            ->component('hr/organization-chart/index')
            ->where('totalCount', 2)
            ->where('chartData.id', $company->id)
            ->where('chartData.children.0.id', $manager->id)
            ->where('chartData.children.0.designation', 'Manager')
            ->where('chartData.children.0.children.0.id', $developer->id)
            ->where('chartData.children.0.children.0.status', 'active'));
    }

    public function test_a_reporting_cycle_does_not_loop_forever()
    {
        $company = $this->userWithRole('company');
        $a = User::factory()->create();
        $b = User::factory()->create(['reports_to_id' => $a->id]);
        $a->update(['reports_to_id' => $b->id]);

        $this->actingAs($company)->get(route('hr.organization-chart.index'))->assertOk();
        $this->assertTrue($a->isOrManages($b));
        $this->assertFalse($b->fresh()->isOrManages($company));
    }

    public function test_only_roles_with_the_permission_can_view_the_chart()
    {
        $this->actingAs($this->userWithRole('employee'))->get(route('hr.organization-chart.index'))->assertForbidden();
    }
}
