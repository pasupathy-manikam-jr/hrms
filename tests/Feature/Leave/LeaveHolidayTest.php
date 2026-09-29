<?php

namespace Tests\Feature\Leave;

use App\Models\Branch;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveHolidayTest extends TestCase
{
    use RefreshDatabase;

    // Monday 9 – Friday 13 March 2026: five working days (default Mon–Fri).
    private const MONDAY = '2026-03-09';

    private const FRIDAY = '2026-03-13';

    public function test_full_day_holidays_in_the_employees_branch_are_not_charged()
    {
        $branch = Branch::factory()->create();
        $other = Branch::factory()->create();
        Holiday::factory()->create(['start_date' => '2026-03-11', 'end_date' => '2026-03-11'])->branches()->attach($branch);
        Holiday::factory()->create(['start_date' => '2026-03-12', 'end_date' => '2026-03-12'])->branches()->attach($other);
        Holiday::factory()->create(['start_date' => '2026-03-10', 'end_date' => '2026-03-10', 'is_half_day' => true])->branches()->attach($branch);

        $this->assertSame(4, LeaveApplication::workingDaysBetween(now()->parse(self::MONDAY), now()->parse(self::FRIDAY), $branch->id));
        $this->assertSame(4, LeaveApplication::workingDaysBetween(now()->parse(self::MONDAY), now()->parse(self::FRIDAY), $other->id));
    }

    public function test_recurring_holidays_repeat_in_later_years()
    {
        $branch = Branch::factory()->create();
        Holiday::factory()->create(['start_date' => '2025-03-11', 'end_date' => '2025-03-11', 'is_recurring' => true])->branches()->attach($branch);

        $this->assertSame(4, LeaveApplication::workingDaysBetween(now()->parse(self::MONDAY), now()->parse(self::FRIDAY), $branch->id));
    }
}
