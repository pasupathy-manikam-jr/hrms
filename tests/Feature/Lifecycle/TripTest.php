<?php

namespace Tests\Feature\Lifecycle;

use App\Models\Employee;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TripTest extends TestCase
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
            'purpose' => 'Client Meeting',
            'destination' => 'Mumbai, India',
            'start_date' => '2030-03-01',
            'end_date' => '2030-03-05',
            'status' => 'planned',
            'advance_amount' => '1500.50',
            ...$overrides,
        ];
    }

    public function test_staff_list_with_filters_and_status_counts(): void
    {
        $trip = Trip::factory()->create(['destination' => 'Tokyo, Japan']);
        Trip::factory()->create(['status' => 'completed']);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.trips.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/trips/index')
                ->has('trips.data', 2)
                ->has('employees', 2)
                ->where('statusCounts', ['all' => 2, 'planned' => 1, 'ongoing' => 0, 'completed' => 1, 'cancelled' => 0]));

        $this->get(route('hr.trips.index', ['search' => 'Tokyo']))
            ->assertInertia(fn ($page) => $page->has('trips.data', 1)->where('trips.data.0.id', $trip->id));

        $this->get(route('hr.trips.index', ['status' => 'completed']))
            ->assertInertia(fn ($page) => $page->has('trips.data', 1)->where('trips.data.0.status', 'completed'));
    }

    public function test_employees_see_only_their_own_trips_and_cannot_manage_them(): void
    {
        $user = $this->userWithRole('employee');
        $own = Trip::factory()->create(['employee_id' => Employee::factory()->create(['user_id' => $user->id])->id]);
        Trip::factory()->create();

        $this->actingAs($user)
            ->get(route('hr.trips.index'))
            ->assertInertia(fn ($page) => $page->has('trips.data', 1)->where('trips.data.0.id', $own->id)->where('statusCounts.all', 1));

        $this->post(route('hr.trips.store'), $this->payload($own->employee))->assertForbidden();
        $this->put(route('hr.trips.update', $own), $this->payload($own->employee))->assertForbidden();
        $this->delete(route('hr.trips.destroy', $own))->assertForbidden();
    }

    public function test_status_advance_expenses_report_and_document(): void
    {
        Storage::fake('local');
        $hr = $this->userWithRole('hr');
        $employee = Employee::factory()->create();

        $this->actingAs($hr)->post(route('hr.trips.store'), [...$this->payload($employee), 'document' => UploadedFile::fake()->create('itinerary.pdf', 5, 'application/pdf')])
            ->assertSessionHasNoErrors();
        $trip = Trip::sole();
        $this->get(route('hr.trips.document', $trip))->assertDownload('itinerary.pdf');

        $this->put(route('hr.trips.change-status', $trip), ['status' => 'ongoing'])->assertSessionHasNoErrors();
        $this->assertSame(['ongoing', $hr->id], [$trip->fresh()?->status, $trip->fresh()?->approved_by]);

        $this->put(route('hr.trips.advance', $trip), ['advance_amount' => 1500, 'advance_status' => 'paid'])->assertSessionHasNoErrors();
        $this->put(route('hr.trips.expenses', $trip), ['total_expenses' => 1800.5, 'reimbursement_status' => 'approved'])->assertSessionHasNoErrors();
        $this->put(route('hr.trips.report', $trip), ['trip_report' => 'Signed the partner agreement.'])->assertSessionHasNoErrors();
        $trip->refresh();
        $this->assertSame(['1500.00', 'paid', '1800.50', 'approved', 'Signed the partner agreement.'], [$trip->advance_amount, $trip->advance_status, $trip->total_expenses, $trip->reimbursement_status, $trip->trip_report]);

        // A cancelled trip has no money to manage.
        $trip->update(['status' => 'cancelled']);
        $this->put(route('hr.trips.advance', $trip), ['advance_amount' => 1, 'advance_status' => 'requested'])->assertForbidden();
    }

    public function test_travellers_can_request_money_but_not_approve_it_or_change_status(): void
    {
        $user = $this->userWithRole('employee');
        $trip = Trip::factory()->create(['employee_id' => Employee::factory()->create(['user_id' => $user->id])->id, 'status' => 'planned']);
        $this->actingAs($user);

        $this->put(route('hr.trips.advance', $trip), ['advance_amount' => 500, 'advance_status' => 'requested'])->assertSessionHasNoErrors();
        $this->put(route('hr.trips.advance', $trip), ['advance_amount' => 500, 'advance_status' => 'paid'])->assertForbidden();
        $this->put(route('hr.trips.expenses', $trip), ['total_expenses' => 450, 'reimbursement_status' => 'pending'])->assertSessionHasNoErrors();
        $this->put(route('hr.trips.expenses', $trip), ['total_expenses' => 450, 'reimbursement_status' => 'approved'])->assertForbidden();
        $this->put(route('hr.trips.report', $trip), ['trip_report' => 'Went well.'])->assertSessionHasNoErrors();
        $this->put(route('hr.trips.change-status', $trip), ['status' => 'completed'])->assertForbidden();

        $this->assertSame(['requested', 'pending', 'planned'], [$trip->fresh()?->advance_status, $trip->fresh()?->reimbursement_status, $trip->fresh()?->status]);

        $other = Trip::factory()->create();
        $this->put(route('hr.trips.report', $other), ['trip_report' => 'x'])->assertNotFound();
        $this->get(route('hr.trips.document', $other))->assertNotFound();
    }

    public function test_validation_and_crud(): void
    {
        $employee = Employee::factory()->create();
        $this->actingAs($this->userWithRole('company'));

        $this->post(route('hr.trips.store'), $this->payload($employee, ['end_date' => '2030-02-01', 'status' => 'lost', 'advance_amount' => -5]))
            ->assertSessionHasErrors(['end_date', 'status', 'advance_amount']);

        $this->post(route('hr.trips.store'), $this->payload($employee))->assertSessionHasNoErrors();
        $trip = Trip::sole();
        $this->assertSame('1500.50', $trip->advance_amount);

        $this->put(route('hr.trips.update', $trip), $this->payload($employee, ['status' => 'completed', 'total_expenses' => '2000']))->assertSessionHasNoErrors();
        $trip->refresh();
        $this->assertSame('completed', $trip->status);
        $this->assertSame('2000.00', $trip->total_expenses);

        $this->delete(route('hr.trips.destroy', $trip))->assertSessionHasNoErrors();
        $this->assertModelMissing($trip);
    }
}
