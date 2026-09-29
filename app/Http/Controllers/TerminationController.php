<?php

namespace App\Http\Controllers;

use App\Models\Termination;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TerminationController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        $query = Termination::query()
            ->visibleTo($user)
            ->with('employee:id,user_id,employee_id,employee_status,gender', 'employee.user:id,name,email,avatar_path', 'approver:id,name')
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when(in_array($request->input('termination_type'), Termination::TYPES, true), fn ($q) => $q->where('termination_type', $request->input('termination_type')))
            ->when($request->date('date_from'), fn ($q, $date) => $q->whereDate('termination_date', '>=', $date))
            ->when($request->date('date_to'), fn ($q, $date) => $q->whereDate('termination_date', '<=', $date))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('reason', 'like', "%{$search}%")
                ->orWhereHas('employee.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))));

        $counts = TableQuery::countBy($query, 'status');

        $query->when(in_array($request->input('status'), Termination::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/terminations/index', [
            'terminations' => TableQuery::paginate($query, $request, [], ['notice_date', 'termination_date', 'status', 'created_at']),
            'employees' => Termination::employeeOptions($user),
            'terminationTypes' => Termination::TYPES,
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(Termination::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['status', 'employee_id', 'termination_type', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        (new Termination([...$this->validated($request), 'status' => 'planned']))->attachUploadFrom($request)->save();

        return $this->done(__('Termination created successfully.'));
    }

    public function update(Request $request, Termination $termination): RedirectResponse
    {
        $this->authorizeOpen($request, $termination);

        $termination->fill($this->validated($request))->attachUploadFrom($request)->save();
        $termination->completeIfDue();

        return $this->done(__('Termination updated successfully.'));
    }

    public function destroy(Request $request, Termination $termination): RedirectResponse
    {
        abort_unless($termination->isVisibleTo($request->user()), 404);

        $termination->delete();

        return $this->done(__('Termination deleted successfully.'));
    }

    /**
     * Approving ("in progress" / "completed") needs approve-terminations; sending it back to "planned" needs
     * reject-terminations. An approved termination whose date has arrived completes and terminates the employee.
     */
    public function changeStatus(Request $request, Termination $termination): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($termination->isVisibleTo($user), 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(Termination::STATUSES)],
            'exit_interview_conducted' => ['boolean'],
            'exit_interview_date' => ['nullable', 'date_format:Y-m-d'],
            'exit_feedback' => ['nullable', 'string', 'max:2000'],
        ]);
        $exitInterview = [
            'exit_interview_conducted' => (bool) ($data['exit_interview_conducted'] ?? false),
            'exit_interview_date' => $data['exit_interview_date'] ?? null,
            'exit_feedback' => $data['exit_feedback'] ?? null,
        ];

        // A completed termination has already terminated the employee: only its exit interview can still be recorded.
        if ($termination->status === 'completed') {
            abort_unless($data['status'] === 'completed', 403, __('Completed terminations cannot be changed.'));
            $termination->update($exitInterview);

            return $this->done(__('Termination status updated.'));
        }

        $status = $data['status'];
        $approving = $status !== 'planned';
        abort_unless($user->can($approving ? 'approve-terminations' : 'reject-terminations'), 403);

        $termination->update([
            ...$exitInterview,
            'status' => $status,
            'approved_by' => $approving ? $user->id : null,
            'approved_at' => $approving ? now() : null,
        ]);
        $termination->completeIfDue();

        return $this->done(__('Termination status updated.'));
    }

    /**
     * Completed terminations have already changed the employee's status, so they are final.
     */
    private function authorizeOpen(Request $request, Termination $termination): void
    {
        abort_unless($termination->isVisibleTo($request->user()), 404);
        abort_if($termination->status === 'completed', 403, __('Completed terminations cannot be changed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'termination_type' => ['required', Rule::in(Termination::TYPES)],
            'notice_date' => ['required', 'date_format:Y-m-d'],
            'termination_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:notice_date'],
            'notice_period' => ['nullable', 'string', 'max:50'],
            'reason' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'document' => Termination::uploadRules(),
        ]);
        unset($data['document']);

        return $data;
    }

    /**
     * Stream the termination's supporting document to someone allowed to see it.
     */
    public function document(Request $request, Termination $termination): StreamedResponse
    {
        abort_unless($termination->isVisibleTo($request->user()), 404);

        return $termination->downloadUpload();
    }
}
