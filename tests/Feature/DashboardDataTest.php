<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Employee;
use App\Models\LeaveApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_dashboard_reflects_live_records()
    {
        $user = $this->userWithRole();
        Employee::factory()->count(3)->create(['date_of_joining' => now()->startOfMonth()]);
        Employee::factory()->create(['employee_status' => 'terminated', 'date_of_joining' => '2024-03-10']);
        Candidate::factory()->count(2)->create(['status' => 'Offer']);

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('dashboardData.stats.totalEmployees', 3)
            ->where('dashboardData.stats.newEmployeesThisMonth', 3)
            ->where('dashboardData.charts.candidateStatusStats.3.name', 'Offer')
            ->where('dashboardData.charts.candidateStatusStats.3.value', 2)
            ->has('dashboardData.charts.hiringTrend', 12)
            ->where('dashboardData.charts.hiringYear', now()->year)
            ->where('dashboardData.charts.availableYears', fn ($years) => collect($years)->contains(2024)));
    }

    public function test_year_pickers_accept_known_years_and_ignore_others()
    {
        Employee::factory()->create(['date_of_joining' => '2024-03-10']);
        $this->actingAs($this->userWithRole());

        $this->get(route('dashboard', ['hiring_year' => 2024]))->assertInertia(fn ($page) => $page
            ->where('dashboardData.charts.hiringYear', 2024)
            ->where('dashboardData.charts.hiringTrend.2.hires', 1));

        $this->get(route('dashboard', ['hiring_year' => 1999, 'payroll_year' => 'abc']))->assertInertia(fn ($page) => $page
            ->where('dashboardData.charts.hiringYear', now()->year)
            ->where('dashboardData.charts.payrollYear', now()->year));
    }

    public function test_pending_leaves_are_counted()
    {
        LeaveApplication::factory()->count(2)->create(['status' => 'pending']);

        $this->actingAs($this->userWithRole())->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('dashboardData.stats.pendingLeaves', 2));
    }

    public function test_employee_dashboard_is_personal()
    {
        $this->actingAs($this->userWithRole('employee'))->get(route('dashboard'))->assertInertia(fn ($page) => $page
            ->component('employee-dashboard')
            ->where('dashboardData.stats.totalAwards', 0)
            ->has('dashboardData.recentActivities.announcements')
            ->where('todayAttendance', null)
            ->missing('dashboardData.charts'));
    }
}
