<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeTraining;
use App\Models\TrainingProgram;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TrainingSessionController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $query = TrainingSession::query()
            ->visibleTo($user)
            ->with(['program:id,name', 'trainers:id,user_id,employee_id,gender', 'trainers.user:id,name,email,avatar_path'])
            ->when($request->integer('training_program_id'), fn ($q, $id) => $q->where('training_program_id', $id))
            ->when(in_array($request->input('location_type'), TrainingSession::LOCATION_TYPES, true), fn ($q) => $q->where('location_type', $request->input('location_type')))
            ->when($request->date('date_from'), fn ($q, $date) => $q->whereDate('start_date', '>=', $date))
            ->when($request->date('date_to'), fn ($q, $date) => $q->whereDate('start_date', '<=', $date));

        $counts = TableQuery::countBy($query, 'status');
        $query->when(in_array($request->input('status'), TrainingSession::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/training-sessions/index', [
            'trainingSessions' => TableQuery::paginate($query, $request, ['name', 'location'], ['name', 'start_date', 'end_date', 'status', 'created_at'], 'start_date'),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(TrainingSession::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            'trainingPrograms' => TrainingProgram::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::query()->with('user:id,name')->orderBy('employee_id')->get(['id', 'user_id', 'employee_id'])
                ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->user->name, 'employee_id' => $e->employee_id]),
            'filters' => TableQuery::filters($request, ['training_program_id', 'status', 'location_type', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Visible sessions from the start of last year to the end of next year; months are switched
     * in the browser like the main calendar.
     */
    public function calendar(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $events = TrainingSession::query()
            ->visibleTo($user)
            ->with(['program:id,name', 'trainers:id,user_id,gender', 'trainers.user:id,name,avatar_path'])
            ->whereDate('end_date', '>=', today()->subYear()->startOfYear())
            ->whereDate('start_date', '<=', today()->addYear()->endOfYear())
            ->when($request->integer('training_program_id'), fn ($q, $id) => $q->where('training_program_id', $id))
            ->when(in_array($request->input('status'), TrainingSession::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('start_date')
            ->get()
            ->map(fn (TrainingSession $session) => [
                'id' => $session->id,
                'title' => $session->name,
                'start' => $session->start_date->toDateString(),
                'end' => $session->end_date->toDateString(),
                'start_time' => $session->start_date->format('H:i'),
                'end_time' => $session->end_date->format('H:i'),
                'status' => $session->status,
                'program' => $session->program?->name,
                'location' => $session->location,
                'trainers' => $session->trainers->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->user->name, 'avatar' => $e->user->avatar, 'gender' => $e->gender])->values(),
            ]);

        return Inertia::render('hr/training-sessions/calendar', [
            'calendarEvents' => $events,
            'trainingPrograms' => TrainingProgram::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['training_program_id', 'status']),
        ]);
    }

    /**
     * The session, its trainers and the employees assigned to it.
     */
    public function show(Request $request, TrainingSession $trainingSession): Response
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(TrainingSession::query()->visibleTo($user)->whereKey($trainingSession->id)->exists(), 404);

        return Inertia::render('hr/training-sessions/show', [
            'trainingSession' => $trainingSession->load(['program:id,name', 'trainers:id,user_id,employee_id,gender', 'trainers.user:id,name,email,avatar_path']),
            'participants' => EmployeeTraining::query()->visibleTo($user)->where('training_session_id', $trainingSession->id)
                ->with(['employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path'])->get(['id', 'employee_id', 'training_session_id', 'status', 'score']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $trainers] = $this->validated($request);

        TrainingSession::create($data)->trainers()->sync($trainers);

        return $this->done(__('Training session created successfully.'));
    }

    public function update(Request $request, TrainingSession $trainingSession): RedirectResponse
    {
        $this->ensureVisible($request, $trainingSession);
        [$data, $trainers] = $this->validated($request);

        $trainingSession->update($data);
        $trainingSession->trainers()->sync($trainers);

        return $this->done(__('Training session updated successfully.'));
    }

    public function destroy(Request $request, TrainingSession $trainingSession): RedirectResponse
    {
        $this->ensureVisible($request, $trainingSession);
        $trainingSession->delete();

        return $this->done(__('Training session deleted successfully.'));
    }

    private function ensureVisible(Request $request, TrainingSession $session): void
    {
        abort_unless(TrainingSession::query()->visibleTo($request->user())->whereKey($session->id)->exists(), 404);
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<int>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'training_program_id' => ['required', 'integer', Rule::exists(TrainingProgram::class, 'id')],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'location_type' => ['required', Rule::in(TrainingSession::LOCATION_TYPES)],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'url', 'max:255', 'required_if:location_type,virtual'],
            'status' => ['required', Rule::in(TrainingSession::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'trainer_ids' => ['array'],
            'trainer_ids.*' => ['integer', 'distinct', Rule::exists(Employee::class, 'id')],
        ]);

        $trainers = array_values(array_map('intval', $data['trainer_ids'] ?? []));
        unset($data['trainer_ids']);

        return [$data, $trainers];
    }
}
