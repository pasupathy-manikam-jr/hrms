<?php

namespace Tests\Feature\Payroll;

use App\Models\SalaryComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered()
    {
        SalaryComponent::factory()->create(['name' => 'Transport Allowance']);
        SalaryComponent::factory()->deduction()->percentage('12.00')->create(['name' => 'Provident Fund']);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.salary-components.index', ['type' => 'deduction']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/salary-components/index')
                ->has('salaryComponents.data', 1)
                ->where('salaryComponents.data.0.name', 'Provident Fund')
                ->where('salaryComponents.data.0.percentage_of_basic', '12.00')
                ->where('filters.type', 'deduction'));

        $this->get(route('hr.salary-components.index', ['search' => 'Transport']))
            ->assertInertia(fn ($page) => $page->has('salaryComponents.data', 1));
    }

    public function test_components_can_be_created_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.salary-components.store'), ['name' => '', 'type' => 'bonus', 'calculation_type' => 'percentage', 'status' => 'active'])
            ->assertSessionHasErrors(['name', 'type', 'percentage_of_basic']);
        $this->post(route('hr.salary-components.store'), ['name' => 'HRA', 'type' => 'earning', 'calculation_type' => 'percentage', 'percentage_of_basic' => '140', 'status' => 'active'])
            ->assertSessionHasErrors('percentage_of_basic');

        $this->post(route('hr.salary-components.store'), [
            'name' => 'HRA', 'type' => 'earning', 'calculation_type' => 'percentage',
            'default_amount' => '999', 'percentage_of_basic' => '40', 'is_taxable' => true, 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $component = SalaryComponent::where('name', 'HRA')->firstOrFail();
        // Only the figure the calculation type uses is kept.
        $this->assertSame('0.00', $component->default_amount);
        $this->assertSame('40.00', $component->percentage_of_basic);
        $this->assertTrue($component->is_taxable);

        $this->put(route('hr.salary-components.update', $component), [
            'name' => 'Housing', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_amount' => '1500.50', 'percentage_of_basic' => '40', 'status' => 'inactive',
        ])->assertSessionHasNoErrors();
        $component->refresh();
        $this->assertSame(['Housing', '1500.50', null, 'inactive'], [$component->name, $component->default_amount, $component->percentage_of_basic, $component->status]);

        $this->delete(route('hr.salary-components.destroy', $component));
        $this->assertModelMissing($component);
    }

    public function test_employees_cannot_manage_salary_components()
    {
        $component = SalaryComponent::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.salary-components.index'))->assertForbidden();
        $this->post(route('hr.salary-components.store'), ['name' => 'X'])->assertForbidden();
        $this->put(route('hr.salary-components.update', $component), ['name' => 'X'])->assertForbidden();
        $this->delete(route('hr.salary-components.destroy', $component))->assertForbidden();
        $this->assertModelExists($component);
    }

    public function test_component_can_be_locked_and_unlocked()
    {
        $component = SalaryComponent::factory()->create(['status' => 'active']);
        $this->actingAs($this->userWithRole());

        $this->put(route('hr.salary-components.toggle-status', $component))->assertSessionHasNoErrors();
        $this->assertSame('inactive', $component->fresh()->status);

        $this->actingAs($this->userWithRole('employee'))
            ->put(route('hr.salary-components.toggle-status', $component))
            ->assertForbidden();
    }
}
