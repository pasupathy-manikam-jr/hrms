<?php

namespace Tests\Feature\Hr;

use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeReportsToTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(Designation $designation, array $overrides = []): array
    {
        return [
            'name' => 'Nora Quinn',
            'email' => 'nora@example.com',
            'password' => 'Zx123456',
            'branch_id' => $designation->department->branch_id,
            'department_id' => $designation->department_id,
            'designation_id' => $designation->id,
            'date_of_joining' => '2026-01-15',
            'employment_type' => 'Full-time',
            'employee_status' => 'active',
            ...$overrides,
        ];
    }

    public function test_new_employees_report_to_their_creator_unless_a_manager_is_chosen()
    {
        $company = $this->userWithRole();
        $manager = User::factory()->create();
        $designation = Designation::factory()->create();

        $this->actingAs($company)->post(route('hr.employees.store'), $this->payload($designation))->assertSessionHasNoErrors();
        $this->post(route('hr.employees.store'), $this->payload($designation, ['email' => 'second@example.com', 'reports_to_id' => $manager->id]))->assertSessionHasNoErrors();

        $this->assertSame($company->id, User::where('email', 'nora@example.com')->value('reports_to_id'));
        $this->assertSame($manager->id, User::where('email', 'second@example.com')->value('reports_to_id'));
    }

    public function test_reporting_cycles_are_rejected_and_managers_can_be_cleared()
    {
        $company = $this->userWithRole();
        $designation = Designation::factory()->create();
        $boss = Employee::factory()->create(['designation_id' => $designation->id, 'department_id' => $designation->department_id, 'branch_id' => $designation->department->branch_id]);
        $report = Employee::factory()->create();
        $report->user->update(['reports_to_id' => $boss->user_id]);

        $update = fn (array $overrides) => $this->put(route('hr.employees.update', $boss), $this->payload($designation, [
            'name' => $boss->user->name, 'email' => $boss->user->email, 'password' => '', ...$overrides,
        ]));

        $this->actingAs($company);
        $update(['reports_to_id' => $report->user_id])->assertSessionHasErrors('reports_to_id');
        $update(['reports_to_id' => $boss->user_id])->assertSessionHasErrors('reports_to_id');

        $update(['reports_to_id' => $company->id])->assertSessionHasNoErrors();
        $this->assertSame($company->id, $boss->user->fresh()->reports_to_id);

        $update(['reports_to_id' => null])->assertSessionHasNoErrors();
        $this->assertNull($boss->user->fresh()->reports_to_id);
    }
}
