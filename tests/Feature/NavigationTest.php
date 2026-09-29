<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_open_every_sidebar_page()
    {
        $this->withoutVite();
        $this->actingAs($this->userWithRole('company'));

        // Every sidebar entry is listed (name => [uri, title, permission]) in routes/hrm.php.
        preg_match_all("/^\\s+'([\\w.-]+)' => \\['/m", (string) file_get_contents(base_path('routes/hrm.php')), $matches);
        $this->assertGreaterThan(80, count($matches[1]));

        foreach ($matches[1] as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    public function test_module_pages_require_their_permission()
    {
        $this->actingAs($this->userWithRole('hr'));
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('hr.employees.index'))->assertOk();

        $this->actingAs($this->userWithRole('employee'));
        $this->get(route('hr.salary-components.index'))->assertForbidden();
        $this->get(route('hr.leave-applications.index'))->assertOk();
    }

    public function test_permissions_are_shared_with_the_frontend()
    {
        $this->actingAs($this->userWithRole('employee'))
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('manage-leave-applications')
                    && ! collect($permissions)->contains('manage-users')));
    }

    public function test_dashboard_receives_dashboard_data()
    {
        $this->actingAs($this->userWithRole())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->has('dashboardData.stats')
                ->has('dashboardData.charts.hiringTrend', 12));
    }

    public function test_employees_get_their_personal_dashboard_without_company_figures()
    {
        $this->actingAs($this->userWithRole('employee'))
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('employee-dashboard')
                ->has('dashboardData.stats.totalAwards')
                ->missing('dashboardData.charts')
                ->missing('dashboardData.stats.totalPayrollThisMonth'));
    }
}
