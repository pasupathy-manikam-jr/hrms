<?php

namespace Tests\Feature\Recruitment;

use App\Models\InterviewType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterviewTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered_by_status()
    {
        InterviewType::factory()->count(3)->create();
        InterviewType::factory()->create(['name' => 'Zeta Panel', 'status' => 'inactive']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.recruitment.interview-types.index', ['search' => 'Zeta']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/interview-types/index')
                ->has('interviewTypes.data', 1)
                ->where('interviewTypes.data.0.name', 'Zeta Panel'));

        $this->get(route('hr.recruitment.interview-types.index', ['status' => 'inactive']))
            ->assertInertia(fn ($page) => $page->has('interviewTypes.data', 1)->where('filters.status', 'inactive'));
    }

    public function test_interview_types_can_be_created_updated_and_deleted()
    {
        $user = $this->userWithRole();
        $this->actingAs($user);

        $this->post(route('hr.recruitment.interview-types.store'), ['name' => '', 'status' => 'bogus'])->assertSessionHasErrors(['name', 'status']);
        $this->post(route('hr.recruitment.interview-types.store'), ['name' => 'Design', 'status' => 'active'])->assertSessionHasNoErrors();

        $record = InterviewType::where('name', 'Design')->firstOrFail();
        $this->assertSame($user->id, $record->created_by);

        $this->put(route('hr.recruitment.interview-types.update', $record), ['name' => 'Design Review', 'status' => 'inactive'])->assertSessionHasNoErrors();
        $this->assertSame('Design Review', $record->fresh()->name);

        $before = $record->fresh()->status;
        $this->put(route('hr.recruitment.interview-types.toggle-status', $record))->assertSessionHasNoErrors();
        $this->assertNotSame($before, $record->fresh()->status);

        $this->delete(route('hr.recruitment.interview-types.destroy', $record));
        $this->assertModelMissing($record);
    }

    public function test_employees_cannot_manage_interview_types()
    {
        $record = InterviewType::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.recruitment.interview-types.index'))->assertForbidden();
        $this->post(route('hr.recruitment.interview-types.store'), ['name' => 'X', 'status' => 'active'])->assertForbidden();
        $this->put(route('hr.recruitment.interview-types.toggle-status', $record))->assertForbidden();
        $this->delete(route('hr.recruitment.interview-types.destroy', $record))->assertForbidden();
        $this->assertModelExists($record);
    }

    public function test_manage_own_users_only_see_their_own_records()
    {
        $user = $this->userWithRole('hr');
        $user->syncRoles([])->givePermissionTo(['manage-interview-types', 'manage-own-interview-types', 'edit-interview-types']);
        $mine = InterviewType::factory()->create(['created_by' => $user->id]);
        $other = InterviewType::factory()->create();

        $this->actingAs($user)
            ->get(route('hr.recruitment.interview-types.index'))
            ->assertInertia(fn ($page) => $page->has('interviewTypes.data', 1)->where('interviewTypes.data.0.id', $mine->id));

        $this->put(route('hr.recruitment.interview-types.update', $other), ['name' => 'X', 'status' => 'active'])->assertForbidden();
    }
}
