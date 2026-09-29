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

class ClockInOutTest extends TestCase
{
    use RefreshDatabase;

    private function employee(array $shift = []): Employee
    {
        return Employee::factory()->create([
            'user_id' => $this->userWithRole('employee')->id,
            'shift_id' => Shift::factory()->create($shift)->id, // 09:00-18:00, 15 min grace, 60 min break
        ]);
    }

    public function test_clocking_in_on_time_and_out_early()
    {
        $employee = $this->employee();
        $this->actingAs($employee->user);

        Carbon::setTestNow('2026-09-16 09:10:00');
        $this->post(route('attendance.clock-in'))->assertSessionHasNoErrors();

        $record = $employee->todayAttendance();
        $this->assertNotNull($record);
        $this->assertSame('09:10', substr($record->clock_in, 0, 5));
        $this->assertSame('present', $record->status);
        $this->assertFalse($record->is_late);

        Carbon::setTestNow('2026-09-16 17:10:00');
        $this->post(route('attendance.clock-out'))->assertSessionHasNoErrors();

        $record->refresh();
        $this->assertSame('17:10', substr($record->clock_out, 0, 5));
        $this->assertTrue($record->is_early_departure);
        $this->assertSame(7.0, $record->total_hours);
    }

    public function test_clocking_in_after_the_grace_period_is_late_in_the_settings_timezone()
    {
        Setting::put(['defaultTimezone' => 'Asia/Kolkata']);
        $employee = $this->employee();
        $this->actingAs($employee->user);

        // 03:46 UTC is 09:16 in Kolkata: one minute past the 15 minute grace.
        Carbon::setTestNow(Carbon::parse('2026-09-16 03:46:00', 'UTC'));
        $this->post(route('attendance.clock-in'))->assertSessionHasNoErrors();

        $record = $employee->todayAttendance();
        $this->assertSame('09:16', substr($record->clock_in, 0, 5));
        $this->assertTrue($record->is_late);
    }

    public function test_a_second_clock_in_is_rejected()
    {
        $employee = $this->employee();
        $this->actingAs($employee->user);
        Carbon::setTestNow('2026-09-16 09:00:00');

        $this->post(route('attendance.clock-in'))->assertSessionHasNoErrors();
        $this->post(route('attendance.clock-in'))->assertSessionHasErrors('attendance');
        $this->assertSame(1, AttendanceRecord::count());
    }

    public function test_clocking_out_without_clocking_in_is_rejected()
    {
        $employee = $this->employee();
        $this->actingAs($employee->user);
        Carbon::setTestNow('2026-09-16 18:00:00');

        $this->post(route('attendance.clock-out'))->assertSessionHasErrors('attendance');
        $this->assertSame(0, AttendanceRecord::count());

        // Nor twice.
        AttendanceRecord::factory()->for($employee)->create(['date' => '2026-09-16']);
        $this->post(route('attendance.clock-out'))->assertSessionHasErrors('attendance');
    }

    public function test_night_shift_clocks_out_after_midnight()
    {
        $employee = $this->employee(['start_time' => '22:00', 'end_time' => '07:00', 'is_night_shift' => true]);
        $this->actingAs($employee->user);

        Carbon::setTestNow('2026-09-16 22:05:00');
        $this->post(route('attendance.clock-in'))->assertSessionHasNoErrors();
        Carbon::setTestNow('2026-09-17 07:00:00');
        $this->post(route('attendance.clock-out'))->assertSessionHasNoErrors();

        $record = AttendanceRecord::sole();
        $this->assertSame('2026-09-16', $record->date->toDateString());
        $this->assertFalse($record->is_late);
        $this->assertFalse($record->is_early_departure);
        $this->assertSame(7.92, $record->total_hours);
    }

    public function test_users_without_an_employee_record_are_rejected()
    {
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('attendance.clock-in'))->assertSessionHasErrors('attendance');
        $this->post(route('attendance.clock-out'))->assertSessionHasErrors('attendance');
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_clocking_requires_the_clock_in_out_permission()
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        $this->post(route('attendance.clock-in'))->assertForbidden();
        $this->post(route('attendance.clock-out'))->assertForbidden();
    }
}
