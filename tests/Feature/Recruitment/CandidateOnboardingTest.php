<?php

namespace Tests\Feature\Recruitment;

use App\Models\Candidate;
use App\Models\CandidateOnboarding;
use App\Models\ChecklistItem;
use App\Models\Employee;
use App\Models\OnboardingChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function checklist(): OnboardingChecklist
    {
        $checklist = OnboardingChecklist::create(['name' => 'Standard']);
        foreach ([['Sign contract', 0, 2], ['Laptop setup', 1, 1], ['Orientation', 3, 3]] as [$name, $day, $order]) {
            ChecklistItem::create(['checklist_id' => $checklist->id, 'task_name' => $name, 'category' => 'HR', 'due_day' => $day, 'sort_order' => $order]);
        }

        return $checklist;
    }

    private function onboarding(array $attributes = []): CandidateOnboarding
    {
        return CandidateOnboarding::start($attributes + [
            'candidate_id' => Candidate::factory()->create(['status' => 'Hired'])->id,
            'checklist_id' => $this->checklist()->id,
            'start_date' => '2026-01-12',
        ]);
    }

    public function test_starting_onboarding_copies_the_checklist_into_dated_tasks()
    {
        $user = $this->userWithRole('hr');
        $candidate = Candidate::factory()->create(['status' => 'Hired']);
        $checklist = $this->checklist();
        // Switched-off template items aren't copied.
        ChecklistItem::create(['checklist_id' => $checklist->id, 'task_name' => 'Retired task', 'category' => 'HR', 'due_day' => 0, 'status' => 'inactive']);
        $buddy = Employee::factory()->create();
        $this->actingAs($user);

        $this->post(route('hr.recruitment.candidate-onboarding.store'), [
            'candidate_id' => $candidate->id, 'checklist_id' => $checklist->id, 'start_date' => '2026-01-12', 'buddy_employee_id' => $buddy->id,
        ])->assertSessionHasNoErrors();

        $onboarding = CandidateOnboarding::where('candidate_id', $candidate->id)->firstOrFail();
        $this->assertSame($user->id, $onboarding->created_by);
        $this->assertSame(
            [['Laptop setup', '2026-01-13'], ['Sign contract', '2026-01-12'], ['Orientation', '2026-01-15']],
            $onboarding->tasks->map(fn ($task) => [$task->task_name, $task->due_date->toDateString()])->all(),
        );
        $this->assertSame(['Pending', 0], [$onboarding->status, $onboarding->progress]);

        // Later template edits don't touch tasks already copied.
        $checklist->items()->delete();
        $this->assertCount(3, $onboarding->fresh()->tasks);

        // Moving the start date moves the due dates.
        $this->put(route('hr.recruitment.candidate-onboarding.update', $onboarding), ['start_date' => '2026-02-02'])->assertSessionHasNoErrors();
        $this->assertSame('2026-02-05', $onboarding->fresh()->tasks->last()->due_date->toDateString());
    }

    public function test_only_hired_candidates_can_be_onboarded_once()
    {
        $this->actingAs($this->userWithRole());
        $checklist = $this->checklist();
        $applicant = Candidate::factory()->create(['status' => 'Interview']);
        $existing = $this->onboarding();

        $this->post(route('hr.recruitment.candidate-onboarding.store'), ['candidate_id' => $applicant->id, 'checklist_id' => $checklist->id, 'start_date' => '2026-01-12'])
            ->assertSessionHasErrors('candidate_id');
        $this->post(route('hr.recruitment.candidate-onboarding.store'), ['candidate_id' => $existing->candidate_id, 'checklist_id' => $checklist->id, 'start_date' => '2026-01-12'])
            ->assertSessionHasErrors('candidate_id');
        $this->post(route('hr.recruitment.candidate-onboarding.store'), [])->assertSessionHasErrors(['candidate_id', 'checklist_id', 'start_date']);
    }

    public function test_marking_tasks_done_drives_progress_and_status()
    {
        $this->actingAs($this->userWithRole());
        $onboarding = $this->onboarding();
        [$first, $second, $third] = $onboarding->tasks;

        $this->put(route('hr.recruitment.candidate-onboarding.tasks.update', [$onboarding, $first]), ['completed' => true])->assertSessionHasNoErrors();
        $this->assertNotNull($first->fresh()->completed_at);
        $this->assertSame('completed', $first->fresh()->status);

        $this->get(route('hr.recruitment.candidate-onboarding.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/candidate-onboarding/index')
                ->where('candidateOnboarding.data.0.status', 'In Progress')
                ->where('candidateOnboarding.data.0.progress', 33)
                ->where('statusCounts', ['all' => 1, 'Pending' => 0, 'In Progress' => 1, 'Completed' => 0]));

        foreach ([$second, $third] as $task) {
            $this->put(route('hr.recruitment.candidate-onboarding.tasks.update', [$onboarding, $task]), ['completed' => true]);
        }
        $this->assertSame(['Completed', 100], [$onboarding->fresh()->status, $onboarding->fresh()->progress]);

        $this->get(route('hr.recruitment.candidate-onboarding.index', ['status' => 'Completed']))
            ->assertInertia(fn ($page) => $page->has('candidateOnboarding.data', 1));
        $this->get(route('hr.recruitment.candidate-onboarding.index', ['status' => 'Pending']))
            ->assertInertia(fn ($page) => $page->has('candidateOnboarding.data', 0));

        // Reopening a task clears completed_at.
        $this->put(route('hr.recruitment.candidate-onboarding.tasks.update', [$onboarding, $third]), ['completed' => false]);
        $this->assertNull($third->fresh()->completed_at);
        $this->assertSame('In Progress', $onboarding->fresh()->status);

        // A task from another onboarding can't be reached through this one.
        $otherTask = $this->onboarding()->tasks->first();
        $this->put(route('hr.recruitment.candidate-onboarding.tasks.update', [$onboarding, $otherTask]), ['completed' => true])->assertNotFound();

        $this->delete(route('hr.recruitment.candidate-onboarding.destroy', $onboarding));
        $this->assertModelMissing($onboarding);
        $this->assertModelMissing($first);
    }

    public function test_employees_only_see_their_own_onboarding_and_cannot_start_it()
    {
        $employee = $this->userWithRole('employee');
        $mine = $this->onboarding(['created_by' => $employee->id]);
        $other = $this->onboarding();
        $this->actingAs($employee);

        $this->get(route('hr.recruitment.candidate-onboarding.index'))
            ->assertInertia(fn ($page) => $page->has('candidateOnboarding.data', 1)->where('candidateOnboarding.data.0.id', $mine->id));

        // manage-candidate-onboarding-status lets them tick off tasks on their own records only.
        $this->put(route('hr.recruitment.candidate-onboarding.tasks.update', [$mine, $mine->tasks->first()]), ['completed' => true])->assertSessionHasNoErrors();
        $this->put(route('hr.recruitment.candidate-onboarding.tasks.update', [$other, $other->tasks->first()]), ['completed' => true])->assertForbidden();

        $this->post(route('hr.recruitment.candidate-onboarding.store'), [])->assertForbidden();
        $this->put(route('hr.recruitment.candidate-onboarding.update', $mine), ['start_date' => '2026-01-01'])->assertForbidden();
        $this->delete(route('hr.recruitment.candidate-onboarding.destroy', $mine))->assertForbidden();
    }

    public function test_task_status_needs_its_own_permission()
    {
        $onboarding = $this->onboarding();
        $user = $this->userWithRole('hr');
        $user->syncRoles([])->givePermissionTo(['manage-candidate-onboarding', 'manage-any-candidate-onboarding']);

        $this->actingAs($user)
            ->put(route('hr.recruitment.candidate-onboarding.tasks.update', [$onboarding, $onboarding->tasks->first()]), ['completed' => true])
            ->assertForbidden();
    }

    public function test_show_page_has_the_tasks_and_progress_and_is_scoped()
    {
        $employee = $this->userWithRole('employee');
        $mine = $this->onboarding(['created_by' => $employee->id]);
        $mine->tasks->first()->update(['status' => 'completed', 'completed_at' => now()]);
        $other = $this->onboarding();
        $this->withoutVite()->actingAs($employee);

        $this->get(route('hr.recruitment.candidate-onboarding.show', $mine))
            ->assertInertia(fn ($page) => $page
                ->component('hr/recruitment/candidate-onboarding/show')
                ->where('onboarding.id', $mine->id)
                ->has('onboarding.tasks', 3)
                ->where('onboarding.progress', 33)
                ->where('onboarding.status', 'In Progress')
                ->has('onboarding.candidate.email'));

        $this->get(route('hr.recruitment.candidate-onboarding.show', $other))->assertNotFound();
    }
}
