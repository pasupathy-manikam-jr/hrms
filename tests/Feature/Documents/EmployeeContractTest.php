<?php

namespace Tests\Feature\Documents;

use App\Models\ContractType;
use App\Models\Employee;
use App\Models\EmployeeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeContractTest extends TestCase
{
    use RefreshDatabase;

    private function contract(Employee $employee, array $attributes = []): EmployeeContract
    {
        return EmployeeContract::create([
            'contract_number' => 'EMP-'.fake()->unique()->numerify('####'),
            'employee_id' => $employee->id,
            'contract_type_id' => ContractType::firstOrCreate(['name' => 'Permanent'])->id,
            'start_date' => '2025-01-01',
            'basic_salary' => '1000.00',
            'status' => 'active',
            ...$attributes,
        ]);
    }

    public function test_contracts_can_be_created_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole('hr'));
        $employee = Employee::factory()->create();
        $type = ContractType::create(['name' => 'Fixed-term']);
        $valid = ['employee_id' => $employee->id, 'contract_type_id' => $type->id, 'start_date' => '2026-01-01', 'end_date' => '2027-01-01', 'basic_salary' => '88000.50', 'status' => 'active'];

        $this->post(route('hr.contracts.employee-contracts.store'), [...$valid, 'end_date' => '2025-01-01', 'basic_salary' => '1.234', 'status' => 'signed'])
            ->assertSessionHasErrors(['end_date', 'basic_salary', 'status']);
        $this->post(route('hr.contracts.employee-contracts.store'), $valid)->assertSessionHasNoErrors();

        $contract = EmployeeContract::firstOrFail();
        $this->assertSame('88000.50', $contract->basic_salary);
        $this->assertMatchesRegularExpression('/^EMP-\d{4}-\d{4}$/', $contract->contract_number);

        $this->post(route('hr.contracts.employee-contracts.store'), [...$valid, 'contract_number' => $contract->contract_number])->assertSessionHasErrors('contract_number');

        $this->put(route('hr.contracts.employee-contracts.update', $contract), [...$valid, 'status' => 'terminated'])->assertSessionHasNoErrors();
        $this->assertSame('terminated', $contract->fresh()->status);

        $this->delete(route('hr.contracts.employee-contracts.destroy', $contract));
        $this->assertModelMissing($contract);
    }

    public function test_expired_is_derived_from_the_end_date()
    {
        $employee = Employee::factory()->create();
        $expired = $this->contract($employee, ['end_date' => today()->subDay()->toDateString()]);
        $this->contract($employee, ['end_date' => today()->toDateString()]);
        $this->contract($employee, ['end_date' => null]);
        $this->contract($employee, ['end_date' => today()->subYear()->toDateString(), 'status' => 'terminated']);

        $this->assertSame('expired', $expired->status);
        $this->assertSame('active', $expired->getRawOriginal('status'));

        $this->actingAs($this->userWithRole())
            ->get(route('hr.contracts.employee-contracts.index', ['status' => 'expired']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/contracts/employee-contracts/index')
                ->where('statusCounts', ['all' => 4, 'draft' => 0, 'pending_approval' => 0, 'active' => 2, 'expired' => 1, 'terminated' => 1, 'renewed' => 0])
                ->where('stats.near_expiry', 1)
                ->has('employeeContracts.data', 1)
                ->where('employeeContracts.data.0.id', $expired->id)
                ->where('employeeContracts.data.0.status', 'expired'));
    }

    public function test_employees_see_only_their_own_contracts()
    {
        $user = $this->userWithRole('employee');
        $mine = $this->contract(Employee::factory()->create(['user_id' => $user->id]));
        $theirs = $this->contract(Employee::factory()->create());

        $this->actingAs($user)
            ->get(route('hr.contracts.employee-contracts.index'))
            ->assertInertia(fn ($page) => $page
                ->has('employeeContracts.data', 1)
                ->where('employeeContracts.data.0.id', $mine->id)
                ->where('employees', []));

        $this->post(route('hr.contracts.employee-contracts.store'), [])->assertForbidden();
        $this->put(route('hr.contracts.employee-contracts.update', $mine), [])->assertForbidden();
        $this->delete(route('hr.contracts.employee-contracts.destroy', $theirs))->assertForbidden();
        $this->assertModelExists($theirs);
    }

    public function test_contract_status_can_be_updated_to_any_demo_status()
    {
        $contract = $this->contract(Employee::factory()->create(), ['status' => 'draft']);
        $this->actingAs($this->userWithRole());

        $this->put(route('hr.contracts.employee-contracts.change-status', $contract), ['status' => 'signed'])->assertSessionHasErrors('status');
        $this->put(route('hr.contracts.employee-contracts.change-status', $contract), ['status' => 'pending_approval'])->assertSessionHasNoErrors();
        $this->assertSame('pending_approval', $contract->fresh()->status);

        $this->put(route('hr.contracts.employee-contracts.change-status', $contract), ['status' => 'expired']);
        $this->get(route('hr.contracts.employee-contracts.index', ['status' => 'expired']))
            ->assertInertia(fn ($page) => $page->has('employeeContracts.data', 1)->where('statusCounts.expired', 1));

        $this->actingAs($this->userWithRole('employee'))
            ->put(route('hr.contracts.employee-contracts.change-status', $contract), ['status' => 'active'])
            ->assertForbidden();
    }
}
