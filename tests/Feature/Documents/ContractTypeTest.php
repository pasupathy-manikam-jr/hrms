<?php

namespace Tests\Feature\Documents;

use App\Models\ContractType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_types_can_be_listed_created_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole('hr'));
        $valid = ['name' => 'Fixed-term', 'default_duration_months' => 12, 'probation_period_months' => 3, 'notice_period_days' => 30, 'is_renewable' => true, 'status' => 'active'];

        $this->post(route('hr.contracts.contract-types.store'), [...$valid, 'name' => '', 'notice_period_days' => -1])->assertSessionHasErrors(['name', 'notice_period_days']);
        $this->post(route('hr.contracts.contract-types.store'), $valid)->assertSessionHasNoErrors();
        ContractType::create(['name' => 'Permanent', 'status' => 'inactive']);

        $this->get(route('hr.contracts.contract-types.index', ['is_renewable' => 'yes']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/contracts/contract-types/index')
                ->has('contractTypes.data', 1)
                ->where('contractTypes.data.0.contracts_count', 0)
                ->where('statusCounts', ['all' => 1, 'active' => 1, 'inactive' => 0]));

        $type = ContractType::where('name', 'Fixed-term')->firstOrFail();
        $this->put(route('hr.contracts.contract-types.update', $type), [...$valid, 'default_duration_months' => null])->assertSessionHasNoErrors();
        $this->assertNull($type->fresh()->default_duration_months);

        $this->delete(route('hr.contracts.contract-types.destroy', $type));
        $this->assertModelMissing($type);
    }

    public function test_employees_cannot_manage_contract_types()
    {
        $type = ContractType::create(['name' => 'Permanent']);
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.contracts.contract-types.index'))->assertForbidden();
        $this->post(route('hr.contracts.contract-types.store'), ['name' => 'X'])->assertForbidden();
        $this->delete(route('hr.contracts.contract-types.destroy', $type))->assertForbidden();
    }

    public function test_it_can_be_locked_and_unlocked()
    {
        $record = ContractType::create(['name' => 'Seasonal', 'status' => 'active']);
        $this->actingAs($this->userWithRole());

        $this->put(route('hr.contracts.contract-types.toggle-status', $record))->assertSessionHasNoErrors();
        $this->assertSame('inactive', $record->fresh()->status);

        $this->actingAs($this->userWithRole('employee'))
            ->put(route('hr.contracts.contract-types.toggle-status', $record))
            ->assertForbidden();
    }
}
