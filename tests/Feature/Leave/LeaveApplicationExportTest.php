<?php

namespace Tests\Feature\Leave;

use App\Models\LeaveApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveApplicationExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_honours_the_filters_and_status_tab(): void
    {
        $kept = LeaveApplication::factory()->create(['reason' => 'Family trip']);
        LeaveApplication::factory()->create(['reason' => 'Other type']);
        LeaveApplication::factory()->create(['leave_type_id' => $kept->leave_type_id, 'reason' => 'Already approved', 'status' => 'approved']);

        $csv = $this->actingAs($this->userWithRole())
            ->get(route('hr.leave-applications.export', ['leave_type_id' => $kept->leave_type_id, 'status' => 'pending']))
            ->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF".'"Employee ID"', $csv);
        $this->assertStringContainsString('Family trip', $csv);
        $this->assertStringContainsString($kept->employee->employee_id, $csv);
        $this->assertStringNotContainsString('Other type', $csv);
        $this->assertStringNotContainsString('Already approved', $csv);
    }

    public function test_export_needs_permission(): void
    {
        $this->actingAs($this->userWithRole('hr'))->get(route('hr.leave-applications.export'))->assertForbidden();
    }
}
