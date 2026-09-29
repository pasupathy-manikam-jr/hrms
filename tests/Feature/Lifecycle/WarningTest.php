<?php

namespace Tests\Feature\Lifecycle;

use App\Models\Employee;
use App\Models\Warning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WarningTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_see_every_warning_with_filters_and_status_counts()
    {
        $final = Warning::factory()->create(['severity' => 'final']);
        Warning::factory()->create(['severity' => 'verbal', 'status' => 'issued']);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.warnings.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/warnings/index')
                ->has('warnings.data', 2)
                ->has('managers', 1)
                ->where('statusCounts', ['all' => 2, 'draft' => 1, 'issued' => 1, 'acknowledged' => 0, 'expired' => 0]));

        $this->get(route('hr.warnings.index', ['severity' => 'final']))
            ->assertInertia(fn ($page) => $page->has('warnings.data', 1)->where('warnings.data.0.id', $final->id));
    }

    public function test_employees_only_see_warnings_about_themselves()
    {
        $user = $this->userWithRole('employee');
        $own = Warning::factory()->create(['employee_id' => Employee::factory()->create(['user_id' => $user->id])->id]);
        Warning::factory()->create();

        $this->actingAs($user)
            ->get(route('hr.warnings.index'))
            ->assertInertia(fn ($page) => $page->has('warnings.data', 1)->where('warnings.data.0.id', $own->id)->where('managers', []));

        $this->post(route('hr.warnings.store'), [])->assertForbidden();
        $this->put(route('hr.warnings.change-status', $own), ['status' => 'issued'])->assertForbidden();
    }

    public function test_create_validates_and_starts_as_draft()
    {
        $hr = $this->userWithRole('hr');
        $employee = Employee::factory()->create();

        $this->actingAs($hr)
            ->post(route('hr.warnings.store'), ['employee_id' => $employee->id, 'warning_by' => $employee->user_id, 'severity' => 'mild'])
            ->assertSessionHasErrors(['warning_by', 'warning_type', 'subject', 'severity', 'warning_date']);

        $this->post(route('hr.warnings.store'), [
            'employee_id' => $employee->id,
            'warning_by' => $hr->id,
            'warning_type' => 'attendance',
            'subject' => 'Late',
            'severity' => 'written',
            'warning_date' => '2030-01-01',
            'expiry_date' => '2030-07-01',
        ])->assertSessionHasNoErrors();

        $this->assertSame('draft', Warning::query()->sole()->status);
    }

    public function test_improvement_plan_and_document_are_saved_downloaded_and_tracked()
    {
        Storage::fake('local');
        $hr = $this->userWithRole('hr');
        $employee = Employee::factory()->create();
        $payload = [
            'employee_id' => $employee->id, 'warning_by' => $hr->id, 'warning_type' => 'performance',
            'subject' => 'Missed targets', 'severity' => 'final', 'warning_date' => '2030-01-01',
            'has_improvement_plan' => true,
        ];

        $this->actingAs($hr)->post(route('hr.warnings.store'), $payload)
            ->assertSessionHasErrors(['improvement_plan_goals', 'improvement_plan_start_date', 'improvement_plan_end_date']);

        $this->post(route('hr.warnings.store'), [
            ...$payload,
            'improvement_plan_goals' => 'Hit weekly targets',
            'improvement_plan_start_date' => '2030-01-02',
            'improvement_plan_end_date' => '2030-03-31',
            'document' => UploadedFile::fake()->create('notice.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $warning = Warning::query()->sole();
        $this->assertTrue($warning->has_improvement_plan);
        $this->assertSame('notice.pdf', $warning->file_name);
        $this->get(route('hr.warnings.document', $warning))->assertOk()->assertDownload('notice.pdf');

        $plan = ['has_improvement_plan' => true, 'improvement_plan_goals' => 'Hit weekly targets', 'improvement_plan_start_date' => '2030-01-02', 'improvement_plan_end_date' => '2030-03-31'];
        $this->put(route('hr.warnings.improvement-plan', $warning), [...$plan, 'improvement_plan_end_date' => '2029-01-01'])->assertSessionHasErrors('improvement_plan_end_date');
        $this->put(route('hr.warnings.improvement-plan', $warning), [...$plan, 'improvement_plan_progress' => 'On track after week 2'])->assertSessionHasNoErrors();
        $this->assertSame('On track after week 2', $warning->fresh()->improvement_plan_progress);

        // Turning the plan off clears it.
        $this->put(route('hr.warnings.improvement-plan', $warning), ['has_improvement_plan' => false])->assertSessionHasNoErrors();
        $this->assertNull($warning->fresh()->improvement_plan_goals);
        $this->assertNull($warning->fresh()->improvement_plan_progress);

        // Someone who can't see the warning can't fetch its document.
        $this->actingAs($this->userWithRole('employee'))->get(route('hr.warnings.document', $warning))->assertNotFound();
    }

    public function test_draft_is_issued_then_acknowledged()
    {
        $warning = Warning::factory()->create();

        $company = $this->userWithRole('company');
        $this->actingAs($company)
            ->put(route('hr.warnings.change-status', $warning), ['status' => 'bogus'])
            ->assertSessionHasErrors('status');

        $this->put(route('hr.warnings.change-status', $warning), ['status' => 'issued'])->assertSessionHasNoErrors();
        $this->assertSame(['issued', $company->id], [$warning->fresh()?->status, $warning->fresh()?->approved_by]);
        $this->put(route('hr.warnings.update', $warning), [])->assertForbidden();

        // Acknowledging needs the date and the employee's response.
        $this->put(route('hr.warnings.change-status', $warning), ['status' => 'acknowledged'])
            ->assertSessionHasErrors(['acknowledgment_date', 'employee_response']);
        $this->put(route('hr.warnings.change-status', $warning), ['status' => 'acknowledged', 'acknowledgment_date' => '2030-01-05', 'employee_response' => 'Understood'])
            ->assertSessionHasNoErrors();
        $warning->refresh();
        $this->assertSame('acknowledged', $warning->status);
        $this->assertSame('Understood', $warning->employee_response);

        $this->delete(route('hr.warnings.destroy', $warning));
        $this->assertModelMissing($warning);
    }
}
