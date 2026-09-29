<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-16 12:00:00'); // a Wednesday
    }

    public function test_grid_has_one_row_per_employee_and_one_cell_per_day()
    {
        $employee = Employee::factory()->create(['shift_id' => Shift::factory()->create(['name' => 'Morning Shift'])->id]);
        AttendanceRecord::factory()->for($employee)->create(['date' => '2026-09-01', 'status' => 'present', 'clock_in' => '09:25', 'is_late' => true]);
        AttendanceRecord::factory()->for($employee)->create(['date' => '2026-09-02', 'status' => 'half_day']);
        AttendanceRecord::factory()->for($employee)->create(['date' => '2026-08-31', 'status' => 'present']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.attendance-records.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/attendance-records/index')
                ->where('currentMonth', 9)
                ->where('currentYear', 2026)
                ->where('daysInMonth', 30)
                ->has('dayHeaders', 30)
                ->where('dayHeaders.4', ['day' => 5, 'day_name' => 'Sat', 'is_weekend' => true, 'is_future' => false])
                ->where('dayHeaders.16.is_future', true)
                ->has('monthOptions', 12)
                ->where('yearOptions.0.value', '2024')
                ->has('employeeRows.data', 1)
                ->where('employeeRows.data.0.shift', 'Morning Shift')
                ->has('employeeRows.data.0.days', 30)
                ->where('employeeRows.data.0.days.0.status', 'present')
                ->where('employeeRows.data.0.days.0.clock_in', '09:25')
                ->where('employeeRows.data.0.days.0.is_late', true)
                ->where('employeeRows.data.0.days.1.status', 'half_day')
                ->where('employeeRows.data.0.days.2.status', 'absent')
                ->where('employeeRows.data.0.days.4.status', 'day_off')
                ->where('employeeRows.data.0.days.20.status', 'future')
                ->where('employeeRows.data.0.present_days', 1.5)
                ->where('employeeRows.data.0.total_working_days', 22));

        // Another month; weekends follow Settings → Working Days.
        Setting::put(['workingDays' => [0, 1, 2, 3, 4, 5, 6]]);
        $this->get(route('hr.attendance-records.index', ['month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->where('currentMonth', 8)
                ->has('dayHeaders', 31)
                ->where('dayHeaders.0.is_weekend', false)
                ->where('employeeRows.data.0.days.30.status', 'present')
                ->where('employeeRows.data.0.total_working_days', 31));
    }

    public function test_employees_only_see_their_own_row()
    {
        $user = $this->userWithRole('employee');
        $own = Employee::factory()->create(['user_id' => $user->id]);
        Employee::factory()->count(2)->create();

        $this->actingAs($user)
            ->get(route('hr.attendance-records.index'))
            ->assertInertia(fn ($page) => $page
                ->has('employeeRows.data', 1)
                ->where('employeeRows.data.0.id', $own->id)
                ->has('employees', 1));

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.attendance-records.index'))
            ->assertInertia(fn ($page) => $page->has('employeeRows.data', 3)->has('employees', 3));
    }

    public function test_records_can_be_created_edited_and_deleted_from_the_grid()
    {
        $employee = Employee::factory()->create(['shift_id' => Shift::factory()->create()->id]); // 09:00-18:00, 15 min grace, 60 min break
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('hr.attendance-records.store'), ['employee_id' => 999, 'date' => 'soon', 'status' => 'late', 'clock_in' => '25:00'])
            ->assertSessionHasErrors(['employee_id', 'date', 'status', 'clock_in']);
        $this->post(route('hr.attendance-records.store'), ['employee_id' => $employee->id, 'date' => '2026-09-03', 'status' => 'present', 'clock_out' => '18:00'])
            ->assertSessionHasErrors('clock_in');

        $this->post(route('hr.attendance-records.store'), ['employee_id' => $employee->id, 'date' => '2026-09-03', 'status' => 'present', 'clock_in' => '09:30', 'clock_out' => '20:00'])
            ->assertSessionHasNoErrors();
        $record = AttendanceRecord::sole();
        $this->assertTrue($record->is_late);
        $this->assertFalse($record->is_early_departure);
        $this->assertSame(9.5, $record->total_hours);
        $this->assertSame(1.5, $record->overtime_hours);

        // One record per employee per day.
        $this->post(route('hr.attendance-records.store'), ['employee_id' => $employee->id, 'date' => '2026-09-03', 'status' => 'absent'])
            ->assertSessionHasErrors('date');

        $this->put(route('hr.attendance-records.update', $record), ['employee_id' => $employee->id, 'date' => '2026-09-03', 'status' => 'half_day', 'clock_in' => '09:00', 'clock_out' => '13:00', 'notes' => 'Left early'])
            ->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame('half_day', $record->status);
        $this->assertFalse($record->is_late);
        $this->assertTrue($record->is_early_departure);
        $this->assertSame(3.0, $record->total_hours);

        $this->delete(route('hr.attendance-records.destroy', $record));
        $this->assertModelMissing($record);
    }

    public function test_employees_can_view_but_not_change_attendance_records_even_their_own()
    {
        $user = $this->userWithRole('employee');
        $employee = Employee::factory()->create(['user_id' => $user->id]);
        $own = AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'date' => '2026-09-01', 'status' => 'present']);
        $this->actingAs($user);

        $this->withoutVite()->get(route('hr.attendance-records.index'))->assertOk();
        // Corrections go through Attendance Regularizations, which HR approves.
        $this->post(route('hr.attendance-records.store'), ['employee_id' => $employee->id, 'date' => '2026-09-02', 'status' => 'present'])->assertForbidden();
        $this->put(route('hr.attendance-records.update', $own), ['employee_id' => $employee->id, 'date' => '2026-09-01', 'status' => 'absent'])->assertForbidden();
        $this->delete(route('hr.attendance-records.destroy', $own))->assertForbidden();
        $this->assertSame('present', $own->fresh()->status);
    }

    public function test_hr_cannot_reach_records_outside_their_scope_via_the_form()
    {
        $user = $this->userWithRole('hr');
        $this->actingAs($user);

        $this->post(route('hr.attendance-records.store'), ['employee_id' => 999999, 'date' => '2026-09-02', 'status' => 'present'])
            ->assertSessionHasErrors('employee_id');
    }

    public function test_users_without_attendance_permissions_are_denied()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('hr.attendance-records.index'))->assertForbidden();
        $this->post(route('attendance.clock-in'))->assertForbidden();
    }
}
