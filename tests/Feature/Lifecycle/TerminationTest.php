<?php

namespace Tests\Feature\Lifecycle;

use App\Models\Employee;
use App\Models\Termination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TerminationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Employee $employee, array $overrides = []): array
    {
        return [
            'employee_id' => $employee->id,
            'termination_type' => 'retirement',
            'notice_date' => '2030-03-01',
            'termination_date' => '2030-03-31',
            'reason' => 'Normal Retirement',
            ...$overrides,
        ];
    }

    public function test_staff_list_with_filters_and_status_counts(): void
    {
        $retirement = Termination::factory()->create();
        Termination::factory()->create(['termination_type' => 'layoff', 'status' => 'in progress']);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.terminations.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/terminations/index')
                ->has('terminations.data', 2)
                ->where('statusCounts', ['all' => 2, 'planned' => 1, 'in progress' => 1, 'completed' => 0]));

        $this->get(route('hr.terminations.index', ['termination_type' => 'retirement']))
            ->assertInertia(fn ($page) => $page->has('terminations.data', 1)->where('terminations.data.0.id', $retirement->id));

        $this->get(route('hr.terminations.index', ['status' => 'in progress']))
            ->assertInertia(fn ($page) => $page->has('terminations.data', 1)->where('terminations.data.0.status', 'in progress'));
    }

    public function test_employees_see_only_their_own_and_cannot_manage(): void
    {
        $user = $this->userWithRole('employee');
        $own = Termination::factory()->create(['employee_id' => Employee::factory()->create(['user_id' => $user->id])->id]);
        $other = Termination::factory()->create();

        $this->actingAs($user)
            ->get(route('hr.terminations.index'))
            ->assertInertia(fn ($page) => $page->has('terminations.data', 1)->where('terminations.data.0.id', $own->id)->where('employees', []));

        $this->post(route('hr.terminations.store'), $this->payload($own->employee))->assertForbidden();
        $this->put(route('hr.terminations.update', $own), $this->payload($own->employee))->assertForbidden();
        $this->put(route('hr.terminations.change-status', $own), ['status' => 'completed'])->assertForbidden();
        $this->delete(route('hr.terminations.destroy', $other))->assertForbidden();
    }

    public function test_validation_and_crud(): void
    {
        $employee = Employee::factory()->create();
        $this->actingAs($this->userWithRole('company'));

        $this->post(route('hr.terminations.store'), $this->payload($employee, ['termination_type' => 'fired', 'termination_date' => '2030-02-01', 'reason' => '']))
            ->assertSessionHasErrors(['termination_type', 'termination_date', 'reason']);

        $this->post(route('hr.terminations.store'), $this->payload($employee))->assertSessionHasNoErrors();
        $termination = Termination::sole();
        $this->assertSame('planned', $termination->status);

        $this->put(route('hr.terminations.update', $termination), $this->payload($employee, ['reason' => 'Early retirement']))->assertSessionHasNoErrors();
        $this->assertSame('Early retirement', $termination->fresh()?->reason);

        $this->delete(route('hr.terminations.destroy', $termination))->assertSessionHasNoErrors();
        $this->assertModelMissing($termination);
    }

    public function test_approving_a_future_termination_waits_until_its_date(): void
    {
        $employee = Employee::factory()->create(['employee_status' => 'active']);
        $termination = Termination::factory()->create(['employee_id' => $employee->id, 'termination_date' => now()->addWeek()->toDateString()]);

        $this->actingAs($this->userWithRole('hr'))
            ->put(route('hr.terminations.change-status', $termination), ['status' => 'in progress'])
            ->assertSessionHasNoErrors();

        $this->assertSame('in progress', $termination->fresh()?->status);
        $this->assertSame('active', $employee->fresh()?->employee_status);

        $this->travel(8)->days();
        $this->assertSame(0, Artisan::call('terminations:complete-due'));

        $this->assertSame('completed', $termination->refresh()->status);
        $this->assertSame('terminated', $employee->refresh()->employee_status);
    }

    public function test_approving_a_past_termination_terminates_the_employee_immediately(): void
    {
        $employee = Employee::factory()->create(['employee_status' => 'active']);
        $termination = Termination::factory()->create(['employee_id' => $employee->id, 'notice_date' => '2020-01-01', 'termination_date' => '2020-02-01']);
        $unapproved = Termination::factory()->create(['termination_date' => '2020-02-01']);

        $this->actingAs($this->userWithRole('hr'))
            ->put(route('hr.terminations.change-status', $termination), ['status' => 'in progress'])
            ->assertSessionHasNoErrors();

        $this->assertSame('completed', $termination->fresh()?->status);
        $this->assertSame('terminated', $employee->fresh()?->employee_status);

        // Unapproved terminations never touch the employee, even when overdue.
        Termination::completeDue();
        $this->assertSame('planned', $unapproved->fresh()?->status);
        $this->assertSame('active', $unapproved->employee->fresh()?->employee_status);

        // Completed terminations are final, but the exit interview can still be recorded.
        $this->put(route('hr.terminations.change-status', $termination), ['status' => 'planned'])->assertForbidden();
        $this->put(route('hr.terminations.change-status', $termination), [
            'status' => 'completed', 'exit_interview_conducted' => true, 'exit_interview_date' => '2020-02-01', 'exit_feedback' => 'Thanked the team',
        ])->assertSessionHasNoErrors();
        $termination->refresh();
        $this->assertSame([true, 'Thanked the team', 'completed'], [$termination->exit_interview_conducted, $termination->exit_feedback, $termination->status]);
    }

    public function test_documents_are_uploaded_and_downloaded_by_those_who_can_see_them(): void
    {
        Storage::fake('local');
        $employee = Employee::factory()->create();
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('hr.terminations.store'), [
            'employee_id' => $employee->id, 'termination_type' => 'retirement', 'notice_date' => '2030-01-01',
            'termination_date' => '2030-02-01', 'reason' => 'Retiring',
            'document' => UploadedFile::fake()->create('notice.pdf', 5, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $termination = Termination::sole();
        $this->get(route('hr.terminations.document', $termination))->assertDownload('notice.pdf');
        $this->actingAs($this->userWithRole('employee'))->get(route('hr.terminations.document', $termination))->assertNotFound();
    }
}
