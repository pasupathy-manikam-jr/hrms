<?php

namespace Tests\Feature\Lifecycle;

use App\Models\Employee;
use App\Models\Resignation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResignationTest extends TestCase
{
    use RefreshDatabase;

    private function employeeFor(User $user): Employee
    {
        return Employee::factory()->create(['user_id' => $user->id]);
    }

    public function test_staff_see_every_resignation_with_filters_and_status_counts(): void
    {
        $mine = Resignation::factory()->create();
        Resignation::factory()->create(['status' => 'approved']);
        Resignation::factory()->create(['status' => 'rejected']);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.resignations.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/resignations/index')
                ->has('resignations.data', 3)
                ->has('employees', 3)
                ->where('statusCounts', ['all' => 3, 'pending' => 1, 'approved' => 1, 'rejected' => 1, 'completed' => 0]));

        $this->get(route('hr.resignations.index', ['status' => 'approved']))
            ->assertInertia(fn ($page) => $page->has('resignations.data', 1)->where('resignations.data.0.status', 'approved'));

        $this->get(route('hr.resignations.index', ['employee_id' => $mine->employee_id]))
            ->assertInertia(fn ($page) => $page->has('resignations.data', 1)->where('resignations.data.0.id', $mine->id));
    }

    public function test_employee_submits_their_own_resignation_and_sees_only_their_own(): void
    {
        $user = $this->userWithRole('employee');
        $employee = $this->employeeFor($user);
        $other = Resignation::factory()->create();

        $this->actingAs($user)->post(route('hr.resignations.store'), [
            'employee_id' => $other->employee_id,
            'resignation_date' => '2030-03-01',
            'last_working_day' => '2030-03-31',
            'notice_period' => '1 month',
            'reason' => 'Further Studies',
            'status' => 'approved',
        ])->assertSessionHasNoErrors();

        $own = Resignation::query()->where('reason', 'Further Studies')->sole();
        $this->assertSame($employee->id, $own->employee_id);
        $this->assertSame('pending', $own->status);

        $this->get(route('hr.resignations.index'))
            ->assertInertia(fn ($page) => $page
                ->has('resignations.data', 1)
                ->where('resignations.data.0.id', $own->id)
                ->where('employees', [])
                ->where('statusCounts.all', 1));

        $this->put(route('hr.resignations.update', $other), [])->assertNotFound();
        $this->delete(route('hr.resignations.destroy', $other))->assertNotFound();
        $this->put(route('hr.resignations.change-status', $own), ['status' => 'approved'])->assertForbidden();
        $this->assertModelExists($other);
    }

    public function test_employee_can_only_change_pending_resignations(): void
    {
        $user = $this->userWithRole('employee');
        $resignation = Resignation::factory()->create(['employee_id' => $this->employeeFor($user)->id, 'status' => 'approved']);

        $this->actingAs($user)->delete(route('hr.resignations.destroy', $resignation))->assertForbidden();
        $this->assertModelExists($resignation);
    }

    public function test_validation(): void
    {
        $this->actingAs($this->userWithRole('hr'))
            ->post(route('hr.resignations.store'), ['resignation_date' => '2030-03-10', 'last_working_day' => '2030-03-01'])
            ->assertSessionHasErrors(['employee_id', 'last_working_day', 'reason']);

        $this->assertDatabaseEmpty('resignations');
    }

    public function test_staff_create_update_delete(): void
    {
        $employee = Employee::factory()->create();
        $this->actingAs($this->userWithRole('company'));

        $this->post(route('hr.resignations.store'), [
            'employee_id' => $employee->id, 'resignation_date' => '2030-03-01', 'last_working_day' => '2030-03-31', 'reason' => 'Relocation',
        ])->assertSessionHasNoErrors();

        $resignation = Resignation::sole();
        $this->put(route('hr.resignations.update', $resignation), [
            'employee_id' => $employee->id, 'resignation_date' => '2030-03-01', 'last_working_day' => '2030-04-15', 'reason' => 'Relocation abroad',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Relocation abroad', $resignation->fresh()?->reason);

        $this->delete(route('hr.resignations.destroy', $resignation))->assertSessionHasNoErrors();
        $this->assertModelMissing($resignation);
    }

    public function test_resignation_letter_is_uploaded_replaced_and_downloaded_only_by_those_who_see_it(): void
    {
        Storage::fake('local');
        $employee = Employee::factory()->create();
        $payload = ['employee_id' => $employee->id, 'resignation_date' => '2030-03-01', 'last_working_day' => '2030-03-31', 'reason' => 'Relocation'];
        $this->actingAs($this->userWithRole('company'));

        $this->post(route('hr.resignations.store'), [...$payload, 'document' => UploadedFile::fake()->create('script.exe', 5)])
            ->assertSessionHasErrors('document');

        $this->post(route('hr.resignations.store'), [...$payload, 'document' => UploadedFile::fake()->create('letter.pdf', 5, 'application/pdf')])
            ->assertSessionHasNoErrors();
        $resignation = Resignation::sole();
        $first = $resignation->file_path;
        $this->get(route('hr.resignations.document', $resignation))->assertDownload('letter.pdf');

        // Saving without a new file keeps it; a new file replaces (and removes) the old one.
        $this->put(route('hr.resignations.update', $resignation), $payload)->assertSessionHasNoErrors();
        $this->assertSame('letter.pdf', $resignation->fresh()?->file_name);
        $this->put(route('hr.resignations.update', $resignation), [...$payload, 'document' => UploadedFile::fake()->create('final.pdf', 5, 'application/pdf')])
            ->assertSessionHasNoErrors();
        $this->assertSame('final.pdf', $resignation->fresh()?->file_name);
        Storage::disk('local')->assertMissing((string) $first);

        $this->actingAs($this->userWithRole('employee'))->get(route('hr.resignations.document', $resignation))->assertNotFound();
    }

    public function test_hr_approves_and_rejects(): void
    {
        $hr = $this->userWithRole('hr');
        $resignation = Resignation::factory()->create();

        $this->actingAs($hr)->put(route('hr.resignations.change-status', $resignation), ['status' => 'bogus'])->assertSessionHasErrors('status');

        $this->put(route('hr.resignations.change-status', $resignation), ['status' => 'approved'])->assertSessionHasNoErrors();
        $resignation->refresh();
        $this->assertSame('approved', $resignation->status);
        $this->assertSame($hr->id, $resignation->approved_by);

        $this->put(route('hr.resignations.change-status', $resignation), ['status' => 'rejected'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $resignation->fresh()?->status);
    }

    public function test_approval_needs_the_approve_permission(): void
    {
        $user = User::factory()->create();
        $this->userWithRole('hr');
        $user->givePermissionTo(['manage-resignations', 'manage-any-resignations', 'reject-resignations']);
        $resignation = Resignation::factory()->create();

        $this->actingAs($user)->put(route('hr.resignations.change-status', $resignation), ['status' => 'approved'])->assertForbidden();
        $this->put(route('hr.resignations.change-status', $resignation), ['status' => 'rejected'])->assertSessionHasNoErrors();
    }
}
