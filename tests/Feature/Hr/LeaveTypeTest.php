<?php

namespace Tests\Feature\Hr;

use App\Models\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered_by_status()
    {
        LeaveType::factory()->count(3)->create();
        LeaveType::factory()->create(['name' => 'Sabbatical', 'status' => 'inactive']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.leave-types.index', ['search' => 'Sabb']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/leave-types/index')
                ->has('leaveTypes.data', 1)
                ->where('leaveTypes.data.0.name', 'Sabbatical'));

        $this->get(route('hr.leave-types.index', ['status' => 'active', 'sort_field' => 'max_days_per_year']))
            ->assertInertia(fn ($page) => $page->has('leaveTypes.data', 3)->where('filters.status', 'active'));
    }

    public function test_leave_types_can_be_created_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.leave-types.store'), ['name' => '', 'max_days_per_year' => 'x', 'color' => 'red', 'status' => 'bogus'])
            ->assertSessionHasErrors(['name', 'max_days_per_year', 'color', 'status']);

        $this->post(route('hr.leave-types.store'), [
            'name' => 'Study Leave',
            'max_days_per_year' => 5,
            'is_paid' => false,
            'color' => '#123ABC',
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $leaveType = LeaveType::where('name', 'Study Leave')->firstOrFail();
        $this->assertFalse($leaveType->is_paid);

        $this->put(route('hr.leave-types.update', $leaveType), [
            'name' => 'Exam Leave',
            'max_days_per_year' => 7,
            'is_paid' => true,
            'color' => '#123ABC',
            'status' => 'active',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Exam Leave', $leaveType->fresh()->name);
        $this->assertTrue($leaveType->fresh()->is_paid);

        $this->put(route('hr.leave-types.toggle-status', $leaveType))->assertSessionHasNoErrors();
        $this->assertSame('inactive', $leaveType->fresh()->status);

        $this->delete(route('hr.leave-types.destroy', $leaveType));
        $this->assertModelMissing($leaveType);
    }

    public function test_employees_cannot_manage_leave_types()
    {
        $leaveType = LeaveType::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.leave-types.index'))->assertForbidden();
        $this->post(route('hr.leave-types.store'), ['name' => 'X'])->assertForbidden();
        $this->put(route('hr.leave-types.update', $leaveType), ['name' => 'X'])->assertForbidden();
        $this->put(route('hr.leave-types.toggle-status', $leaveType))->assertForbidden();
        $this->delete(route('hr.leave-types.destroy', $leaveType))->assertForbidden();
        $this->assertModelExists($leaveType);
    }
}
