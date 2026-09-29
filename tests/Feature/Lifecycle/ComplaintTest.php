<?php

namespace Tests\Feature\Lifecycle;

use App\Models\Complaint;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplaintTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'complaint_type' => 'Harassment',
            'subject' => 'Intimidation',
            'complaint_date' => '2030-03-01',
            'description' => 'Repeated intimidation in meetings.',
            ...$overrides,
        ];
    }

    private function employeeFor(User $user): Employee
    {
        return Employee::factory()->create(['user_id' => $user->id]);
    }

    public function test_staff_list_with_filters_and_status_counts(): void
    {
        $harassment = Complaint::factory()->create();
        Complaint::factory()->create(['complaint_type' => 'Discrimination', 'status' => 'under investigation']);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.complaints.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/complaints/index')
                ->has('complaints.data', 2)
                ->where('statusCounts', ['all' => 2, 'submitted' => 1, 'under investigation' => 1, 'resolved' => 0, 'dismissed' => 0]));

        $this->get(route('hr.complaints.index', ['complaint_type' => 'Harassment']))
            ->assertInertia(fn ($page) => $page->has('complaints.data', 1)->where('complaints.data.0.id', $harassment->id));
    }

    public function test_employees_see_complaints_they_filed_but_not_ones_against_them(): void
    {
        $user = $this->userWithRole('employee');
        $employee = $this->employeeFor($user);
        $filed = Complaint::factory()->create(['employee_id' => $employee->id]);
        $against = Complaint::factory()->create(['against_employee_id' => $employee->id]);

        $this->actingAs($user)
            ->get(route('hr.complaints.index'))
            ->assertInertia(fn ($page) => $page
                ->has('complaints.data', 1)
                ->where('complaints.data.0.id', $filed->id)
                ->where('statusCounts.all', 1));

        $this->put(route('hr.complaints.update', $against), $this->payload())->assertNotFound();
        $this->delete(route('hr.complaints.destroy', $against))->assertNotFound();
        $this->put(route('hr.complaints.resolve', $filed), ['status' => 'dismissed'])->assertForbidden();
        $this->assertModelExists($against);
    }

    public function test_employee_files_as_themselves_and_edits_only_while_submitted(): void
    {
        $user = $this->userWithRole('employee');
        $employee = $this->employeeFor($user);
        $other = Employee::factory()->create();

        $this->actingAs($user)->post(route('hr.complaints.store'), $this->payload(['employee_id' => $other->id, 'against_employee_id' => $other->id]))
            ->assertSessionHasNoErrors();

        $complaint = Complaint::sole();
        $this->assertSame($employee->id, $complaint->employee_id);
        $this->assertSame($other->id, $complaint->against_employee_id);
        $this->assertSame('submitted', $complaint->status);

        $this->post(route('hr.complaints.store'), $this->payload(['against_employee_id' => $employee->id]))
            ->assertSessionHasErrors('against_employee_id');

        $complaint->update(['status' => 'resolved']);
        $this->put(route('hr.complaints.update', $complaint), $this->payload())->assertForbidden();
        $this->delete(route('hr.complaints.destroy', $complaint))->assertForbidden();
    }

    public function test_staff_validation_and_crud(): void
    {
        $complainant = Employee::factory()->create();
        $this->actingAs($this->userWithRole('company'));

        $this->post(route('hr.complaints.store'), $this->payload(['complaint_type' => 'Other stuff', 'subject' => '']))
            ->assertSessionHasErrors(['employee_id', 'complaint_type', 'subject']);

        $this->post(route('hr.complaints.store'), $this->payload(['employee_id' => $complainant->id]))->assertSessionHasNoErrors();
        $complaint = Complaint::sole();

        $this->put(route('hr.complaints.update', $complaint), $this->payload(['employee_id' => $complainant->id, 'subject' => 'Bullying']))->assertSessionHasNoErrors();
        $this->assertSame('Bullying', $complaint->fresh()?->subject);

        $this->delete(route('hr.complaints.destroy', $complaint))->assertSessionHasNoErrors();
        $this->assertModelMissing($complaint);
    }

    public function test_hr_resolves_a_complaint(): void
    {
        $complaint = Complaint::factory()->create();
        $this->actingAs($this->userWithRole('hr'));

        $this->put(route('hr.complaints.resolve', $complaint), ['status' => 'resolved'])
            ->assertSessionHasErrors(['resolution_action', 'resolution_date']);

        $this->put(route('hr.complaints.resolve', $complaint), ['status' => 'under investigation', 'investigation_notes' => 'Interviewing witnesses'])
            ->assertSessionHasNoErrors();
        $this->assertSame('under investigation', $complaint->fresh()?->status);

        $this->put(route('hr.complaints.resolve', $complaint), [
            'status' => 'resolved', 'resolution_action' => 'Counselling provided', 'resolution_date' => '2030-04-01',
        ])->assertSessionHasNoErrors();

        $complaint->refresh();
        $this->assertSame('resolved', $complaint->status);
        $this->assertSame('Counselling provided', $complaint->resolution_action);
    }

    public function test_status_assignment_follow_up_and_document(): void
    {
        Storage::fake('local');
        $hr = $this->userWithRole('hr');
        $investigator = $this->userWithRole('company');
        $outsider = $this->userWithRole('employee');
        $complainant = Employee::factory()->create();
        $this->actingAs($hr);

        $this->post(route('hr.complaints.store'), [...$this->payload(), 'employee_id' => $complainant->id, 'is_anonymous' => true,
            'document' => UploadedFile::fake()->create('statement.pdf', 5, 'application/pdf')])->assertSessionHasNoErrors();
        $complaint = Complaint::sole();
        $this->assertTrue($complaint->is_anonymous);
        $this->get(route('hr.complaints.document', $complaint))->assertDownload('statement.pdf');

        $this->put(route('hr.complaints.change-status', $complaint), ['status' => 'under investigation'])->assertSessionHasNoErrors();
        $this->assertSame('under investigation', $complaint->fresh()?->status);

        // Only company, HR and manager users can investigate.
        $this->put(route('hr.complaints.assign', $complaint), ['assigned_to' => $outsider->id])->assertSessionHasErrors('assigned_to');
        $this->put(route('hr.complaints.assign', $complaint), ['assigned_to' => $investigator->id, 'resolution_deadline' => '2030-05-01'])->assertSessionHasNoErrors();
        $this->assertSame($investigator->id, $complaint->fresh()?->assigned_to);

        $this->put(route('hr.complaints.follow-up', $complaint), [])->assertSessionHasErrors(['follow_up_action', 'follow_up_date']);
        $this->put(route('hr.complaints.follow-up', $complaint), ['follow_up_action' => 'Check-in meeting', 'follow_up_date' => '2030-06-01', 'feedback' => 'Satisfied'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Satisfied', $complaint->fresh()?->feedback);

        $this->actingAs($outsider);
        $this->put(route('hr.complaints.assign', $complaint), ['assigned_to' => $investigator->id])->assertForbidden();
        $this->put(route('hr.complaints.change-status', $complaint), ['status' => 'resolved'])->assertForbidden();
    }
}
