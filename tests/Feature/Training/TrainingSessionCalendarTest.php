<?php

namespace Tests\Feature\Training;

use App\Models\Employee;
use App\Models\TrainingProgram;
use App\Models\TrainingSession;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingSessionCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function program(string $name): TrainingProgram
    {
        return TrainingProgram::create([
            'training_type_id' => TrainingType::firstOrCreate(['name' => 'Technical'])->id,
            'name' => $name, 'cost' => '100', 'status' => 'active',
        ]);
    }

    private function trainingSession(TrainingProgram $program, array $attributes = []): TrainingSession
    {
        return TrainingSession::create([
            'training_program_id' => $program->id, 'name' => 'Morning Batch',
            'start_date' => '2026-10-01 09:00', 'end_date' => '2026-10-02 13:00',
            'location_type' => 'physical', 'location' => 'Room A', 'status' => 'scheduled', ...$attributes,
        ]);
    }

    public function test_calendar_lists_sessions_as_events_and_applies_filters()
    {
        $this->travelTo('2026-09-27');
        $laravel = $this->program('Laravel');
        $safety = $this->program('Safety');
        $this->trainingSession($laravel);
        $this->trainingSession($safety, ['name' => 'Fire Drill', 'start_date' => '2026-11-05 18:00', 'end_date' => '2026-11-05 20:00', 'status' => 'completed']);
        $this->trainingSession($safety, ['name' => 'Ancient', 'start_date' => '2020-01-01 09:00', 'end_date' => '2020-01-01 10:00']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.training-sessions.calendar'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/training-sessions/calendar')
                ->has('calendarEvents', 2)
                ->where('calendarEvents.0', [
                    'id' => TrainingSession::where('name', 'Morning Batch')->value('id'),
                    'title' => 'Morning Batch', 'start' => '2026-10-01', 'end' => '2026-10-02',
                    'start_time' => '09:00', 'end_time' => '13:00', 'status' => 'scheduled',
                    'program' => 'Laravel', 'location' => 'Room A', 'trainers' => [],
                ])
                ->has('trainingPrograms', 2));

        $this->get(route('hr.training-sessions.calendar', ['training_program_id' => $safety->id]))
            ->assertInertia(fn ($page) => $page->has('calendarEvents', 1)->where('calendarEvents.0.title', 'Fire Drill'));
        $this->get(route('hr.training-sessions.calendar', ['status' => 'scheduled']))
            ->assertInertia(fn ($page) => $page->has('calendarEvents', 1)->where('filters.status', 'scheduled'));
    }

    public function test_employees_only_see_sessions_they_train_and_others_are_denied()
    {
        $user = $this->userWithRole('employee');
        $program = $this->program('Laravel');
        $mine = $this->trainingSession($program, ['start_date' => today()->setTime(9, 0), 'end_date' => today()->setTime(10, 0)]);
        $mine->trainers()->attach(Employee::factory()->create(['user_id' => $user->id]));
        $this->trainingSession($program, ['name' => 'Other', 'start_date' => today()->setTime(9, 0), 'end_date' => today()->setTime(10, 0)]);

        $this->actingAs($user)
            ->get(route('hr.training-sessions.calendar'))
            ->assertInertia(fn ($page) => $page->has('calendarEvents', 1)->where('calendarEvents.0.id', $mine->id));

        $this->actingAs(User::factory()->create())
            ->get(route('hr.training-sessions.calendar'))
            ->assertForbidden();
    }
}
