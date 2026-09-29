<?php

namespace Tests\Feature\Lifecycle;

use App\Models\Designation;
use App\Models\Employee;
use App\Models\Transfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_see_every_transfer_with_filters_and_status_counts()
    {
        $first = Transfer::factory()->create();
        Transfer::factory()->create(['status' => 'approved']);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.transfers.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/transfers/index')
                ->has('transfers.data', 2)
                ->has('employees', 2)
                ->where('statusCounts', ['all' => 2, 'pending' => 1, 'approved' => 1, 'rejected' => 0]));

        $this->get(route('hr.transfers.index', ['branch_id' => $first->to_branch_id]))
            ->assertInertia(fn ($page) => $page->has('transfers.data', 1)->where('transfers.data.0.id', $first->id));
    }

    public function test_employees_only_see_transfers_about_themselves()
    {
        $user = $this->userWithRole('employee');
        $own = Transfer::factory()->create(['employee_id' => Employee::factory()->create(['user_id' => $user->id])->id]);
        Transfer::factory()->create();

        $this->actingAs($user)
            ->get(route('hr.transfers.index'))
            ->assertInertia(fn ($page) => $page->has('transfers.data', 1)->where('transfers.data.0.id', $own->id));

        $this->post(route('hr.transfers.store'), [])->assertForbidden();
        $this->put(route('hr.transfers.approve', $own))->assertForbidden();
    }

    public function test_create_snapshots_the_current_placement_and_validates_the_target()
    {
        Storage::fake('local');
        $employee = Employee::factory()->create();
        $target = Designation::factory()->create();
        $otherDesignation = Designation::factory()->create();

        $this->actingAs($this->userWithRole('hr'))
            ->post(route('hr.transfers.store'), [
                'employee_id' => $employee->id,
                'to_branch_id' => $target->department->branch_id,
                'to_department_id' => $target->department_id,
                'to_designation_id' => $otherDesignation->id, // not in that department
                'transfer_date' => '2030-01-01',
                'effective_date' => '2029-01-01',
            ])
            ->assertSessionHasErrors(['to_designation_id', 'effective_date', 'reason']);

        $this->post(route('hr.transfers.store'), [
            'employee_id' => $employee->id,
            'to_branch_id' => $target->department->branch_id,
            'to_department_id' => $target->department_id,
            'to_designation_id' => $target->id,
            'transfer_date' => '2030-01-01',
            'effective_date' => '2030-02-01',
            'reason' => 'Needed at the new branch',
            'document' => UploadedFile::fake()->create('transfer-order.pdf', 5, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $transfer = Transfer::query()->sole();
        $this->assertSame('pending', $transfer->status);
        $this->assertSame($employee->branch_id, $transfer->from_branch_id);
        $this->assertSame($employee->designation_id, $transfer->from_designation_id);
        $this->get(route('hr.transfers.document', $transfer))->assertDownload('transfer-order.pdf');
    }

    public function test_approving_moves_the_employee_and_rejecting_does_not()
    {
        $approved = Transfer::factory()->create();
        $rejected = Transfer::factory()->create();
        $rejectedBefore = $rejected->employee->only('branch_id', 'department_id', 'designation_id');

        $this->actingAs($this->userWithRole('company'))->put(route('hr.transfers.approve', $approved), ['notes' => 'Welcome aboard'])->assertSessionHasNoErrors();
        // Rejecting needs a reason.
        $this->put(route('hr.transfers.reject', $rejected))->assertSessionHasErrors('notes');
        $this->put(route('hr.transfers.reject', $rejected), ['notes' => 'Position filled locally'])->assertSessionHasNoErrors();
        $this->assertSame('Welcome aboard', $approved->fresh()?->notes);

        $this->assertSame(
            ['branch_id' => $approved->to_branch_id, 'department_id' => $approved->to_department_id, 'designation_id' => $approved->to_designation_id],
            $approved->employee->fresh()?->only('branch_id', 'department_id', 'designation_id'),
        );
        $this->assertSame($rejectedBefore, $rejected->employee->fresh()?->only('branch_id', 'department_id', 'designation_id'));
        $this->assertSame('rejected', $rejected->fresh()?->status);

        $this->put(route('hr.transfers.approve', $approved))->assertForbidden();

        $this->delete(route('hr.transfers.destroy', $approved));
        $this->assertModelMissing($approved);
    }
}
