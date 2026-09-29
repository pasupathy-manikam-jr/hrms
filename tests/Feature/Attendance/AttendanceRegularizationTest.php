<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\AttendanceRegularization;
use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceRegularizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Carbon::setTestNow('2026-09-16 12:00:00');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function request(array $attributes = []): AttendanceRegularization
    {
        return AttendanceRegularization::create([
            'employee_id' => Employee::factory()->create(['shift_id' => Shift::factory()->create()->id])->id,
            'date' => '2026-09-14',
            'requested_clock_in' => '09:30',
            'requested_clock_out' => '19:00',
            'reason' => 'Forgot to clock in',
            'status' => 'pending',
            ...$attributes,
        ]);
    }

    public function test_staff_list_requests_with_filters_and_counts(): void
    {
        $pending = $this->request();
        $this->request(['status' => 'approved']);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.attendance-regularizations.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/attendance-regularizations/index')
                ->has('regularizations.data', 2)
                ->has('employees', 2)
                ->where('statusCounts', ['all' => 2, 'pending' => 1, 'approved' => 1, 'rejected' => 0]));

        $this->get(route('hr.attendance-regularizations.index', ['status' => 'pending']))
            ->assertInertia(fn ($page) => $page->has('regularizations.data', 1)->where('regularizations.data.0.id', $pending->id));
    }

    public function test_employee_requests_their_own_correction_and_sees_only_their_own(): void
    {
        $user = $this->userWithRole('employee');
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        AttendanceRecord::factory()->for($employee)->create(['date' => '2026-09-15', 'clock_in' => '10:10', 'clock_out' => '18:00']);
        $other = $this->request();

        $this->actingAs($user)->post(route('hr.attendance-regularizations.store'), [
            'employee_id' => $other->employee_id,
            'date' => '2026-09-15',
            'requested_clock_in' => '09:00',
            'requested_clock_out' => '18:00',
            'reason' => 'Card reader down',
        ])->assertSessionHasNoErrors();

        $own = AttendanceRegularization::query()->where('reason', 'Card reader down')->sole();
        $this->assertSame($employee->id, $own->employee_id);
        $this->assertSame('pending', $own->status);
        $this->assertSame('10:10', substr((string) $own->original_clock_in, 0, 5));

        // A second pending request for the same day is refused; future days too.
        $this->post(route('hr.attendance-regularizations.store'), ['date' => '2026-09-15', 'requested_clock_in' => '09:00', 'reason' => 'Again'])
            ->assertSessionHasErrors('date');
        $this->post(route('hr.attendance-regularizations.store'), ['date' => '2026-09-20', 'requested_clock_in' => '09:00', 'reason' => 'Future'])
            ->assertSessionHasErrors('date');
        $this->post(route('hr.attendance-regularizations.store'), [])->assertSessionHasErrors(['date', 'requested_clock_in', 'reason']);

        $this->get(route('hr.attendance-regularizations.index'))
            ->assertInertia(fn ($page) => $page
                ->has('regularizations.data', 1)
                ->where('regularizations.data.0.id', $own->id)
                ->where('employees', []));

        $this->put(route('hr.attendance-regularizations.update', $other), ['date' => '2026-09-14', 'requested_clock_in' => '08:00', 'reason' => 'x'])->assertNotFound();
        $this->delete(route('hr.attendance-regularizations.destroy', $other))->assertNotFound();
        $this->put(route('hr.attendance-regularizations.approve', $own))->assertForbidden();

        $this->put(route('hr.attendance-regularizations.update', $own), ['date' => '2026-09-15', 'requested_clock_in' => '08:45', 'reason' => 'Card reader down'])
            ->assertSessionHasNoErrors();
        $this->assertSame('08:45', substr($own->fresh()->requested_clock_in ?? '', 0, 5));

        $own->update(['status' => 'rejected']);
        $this->delete(route('hr.attendance-regularizations.destroy', $own))->assertForbidden();
    }

    public function test_approving_creates_the_missing_attendance_record_with_computed_times(): void
    {
        $request = $this->request(['requested_clock_in' => '09:30', 'requested_clock_out' => '19:00']);
        $user = $this->userWithRole('hr');

        $this->actingAs($user)->put(route('hr.attendance-regularizations.approve', $request), ['manager_comments' => 'OK'])
            ->assertSessionHasNoErrors();

        $record = AttendanceRecord::query()->where('employee_id', $request->employee_id)->sole();
        $this->assertSame('2026-09-14', $record->date->toDateString());
        $this->assertSame('present', $record->status);
        $this->assertTrue($record->is_late); // 09:30 is past 09:00 + 15 min grace
        $this->assertSame(8.5, $record->total_hours); // 9.5h minus the 1h break
        $this->assertSame(0.5, $record->overtime_hours);

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame($record->id, $request->attendance_record_id);
        $this->assertSame($user->id, $request->approved_by);

        // Already decided.
        $this->put(route('hr.attendance-regularizations.reject', $request))->assertSessionHasErrors('status');
    }

    public function test_approving_updates_the_existing_record(): void
    {
        $request = $this->request(['requested_clock_in' => '09:00', 'requested_clock_out' => '18:00']);
        $existing = AttendanceRecord::factory()->create([
            'employee_id' => $request->employee_id, 'date' => '2026-09-14', 'status' => 'absent',
            'clock_in' => '11:00', 'clock_out' => null, 'is_late' => true, 'total_hours' => 0,
        ]);

        $this->actingAs($this->userWithRole('company'))->put(route('hr.attendance-regularizations.approve', $request))->assertSessionHasNoErrors();

        $existing->refresh();
        $this->assertSame(1, AttendanceRecord::query()->count());
        $this->assertSame('present', $existing->status);
        $this->assertSame('09:00', substr((string) $existing->clock_in, 0, 5));
        $this->assertFalse($existing->is_late);
        $this->assertSame(8.0, $existing->total_hours);
    }

    public function test_rejecting_leaves_attendance_alone(): void
    {
        $request = $this->request();

        $this->actingAs($this->userWithRole('hr'))->put(route('hr.attendance-regularizations.reject', $request), ['manager_comments' => 'No proof'])
            ->assertSessionHasNoErrors();

        $this->assertSame('rejected', $request->fresh()?->status);
        $this->assertSame(0, AttendanceRecord::query()->count());
    }
}
