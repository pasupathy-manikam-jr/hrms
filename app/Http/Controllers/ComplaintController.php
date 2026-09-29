<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Employee;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplaintController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        // visibleTo() scopes on the complainant, so employees never see complaints filed against them.
        $query = Complaint::query()
            ->visibleTo($user)
            ->with('employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path', 'againstEmployee:id,user_id,employee_id,gender', 'againstEmployee.user:id,name,email,avatar_path', 'assignee:id,name')
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->integer('against_employee_id'), fn ($q, $id) => $q->where('against_employee_id', $id))
            ->when(in_array($request->input('complaint_type'), Complaint::TYPES, true), fn ($q) => $q->where('complaint_type', $request->input('complaint_type')))
            ->when($request->date('date_from'), fn ($q, $date) => $q->whereDate('complaint_date', '>=', $date))
            ->when($request->date('date_to'), fn ($q, $date) => $q->whereDate('complaint_date', '<=', $date))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('subject', 'like', "%{$search}%")
                ->orWhereHas('employee.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))));

        $counts = TableQuery::countBy($query, 'status');

        $query->when(in_array($request->input('status'), Complaint::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/complaints/index', [
            'complaints' => TableQuery::paginate($query, $request, [], ['complaint_date', 'subject', 'status', 'created_at']),
            // Everyone may name the person a complaint is against, as in the demo.
            'employees' => Employee::query()->with('user:id,name')->orderBy('employee_id')->get(['id', 'user_id', 'employee_id'])
                ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->user->name, 'employee_id' => $e->employee_id]),
            'complaintTypes' => Complaint::TYPES,
            // The demo's investigators: company, HR and manager users.
            'assignees' => $user->can('assign-complaints') ? $this->investigators() : [],
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(Complaint::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['status', 'employee_id', 'against_employee_id', 'complaint_type', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        (new Complaint([...$this->validated($request), 'status' => 'submitted']))->attachUploadFrom($request)->save();

        return $this->done(__('Complaint submitted successfully.'));
    }

    public function update(Request $request, Complaint $complaint): RedirectResponse
    {
        $this->authorizeChange($request, $complaint);

        $complaint->fill($this->validated($request, $complaint))->attachUploadFrom($request)->save();

        return $this->done(__('Complaint updated successfully.'));
    }

    public function destroy(Request $request, Complaint $complaint): RedirectResponse
    {
        $this->authorizeChange($request, $complaint);

        $complaint->delete();

        return $this->done(__('Complaint deleted successfully.'));
    }

    /**
     * The demo's "change status" action.
     */
    public function changeStatus(Request $request, Complaint $complaint): RedirectResponse
    {
        abort_unless($complaint->isVisibleTo($request->user()), 404);

        $complaint->update($request->validate(['status' => ['required', Rule::in(Complaint::STATUSES)]]));

        return $this->done(__('Complaint status updated.'));
    }

    /**
     * Hand the complaint to an investigator, with an optional deadline.
     */
    public function assign(Request $request, Complaint $complaint): RedirectResponse
    {
        abort_unless($complaint->isVisibleTo($request->user()), 404);

        $complaint->update($request->validate([
            'assigned_to' => ['required', 'integer', Rule::in($this->investigators()->modelKeys())],
            'resolution_deadline' => ['nullable', 'date_format:Y-m-d'],
        ]));

        return $this->done(__('Complaint assigned.'));
    }

    /**
     * Record what was done after the resolution and any feedback from the complainant.
     */
    public function followUp(Request $request, Complaint $complaint): RedirectResponse
    {
        abort_unless($complaint->isVisibleTo($request->user()), 404);

        $complaint->update($request->validate([
            'follow_up_action' => ['required', 'string', 'max:2000'],
            'follow_up_date' => ['required', 'date_format:Y-m-d'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ]));

        return $this->done(__('Follow-up updated.'));
    }

    /**
     * @return Collection<int, User>
     */
    private function investigators(): Collection
    {
        return User::query()->whereRelation('roles', fn ($q) => $q->whereIn('name', Complaint::INVESTIGATOR_ROLES))->orderBy('name')->get(['id', 'name']);
    }

    public function document(Request $request, Complaint $complaint): StreamedResponse
    {
        abort_unless($complaint->isVisibleTo($request->user()), 404);

        return $complaint->downloadUpload();
    }

    /**
     * Move a complaint through investigation to a resolution (resolve-complaints).
     */
    public function resolve(Request $request, Complaint $complaint): RedirectResponse
    {
        abort_unless($complaint->isVisibleTo($request->user()), 404);

        $closing = in_array($request->input('status'), ['resolved', 'dismissed'], true);

        $complaint->update($request->validate([
            'status' => ['required', Rule::in(['under investigation', 'resolved', 'dismissed'])],
            'investigation_notes' => ['nullable', 'string', 'max:2000'],
            'resolution_action' => [Rule::requiredIf($closing), 'nullable', 'string', 'max:2000'],
            'resolution_date' => [Rule::requiredIf($closing), 'nullable', 'date_format:Y-m-d'],
            'follow_up_action' => ['nullable', 'string', 'max:2000'],
            'follow_up_date' => ['nullable', 'date_format:Y-m-d'],
        ]));

        return $this->done(__('Complaint status updated.'));
    }

    /**
     * Staff may change any complaint; employees only their own, while still submitted.
     */
    private function authorizeChange(Request $request, Complaint $complaint): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($complaint->isVisibleTo($user), 404);
        abort_unless($user->can('manage-any-complaints') || $complaint->status === 'submitted', 403, __('Only submitted complaints can be changed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Complaint $complaint = null): array
    {
        /** @var User $user */
        $user = $request->user();
        $canActForOthers = $user->can('manage-any-complaints');

        $data = $request->validate([
            'employee_id' => [Rule::requiredIf($canActForOthers), 'integer', Rule::exists('employees', 'id')],
            'against_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'complaint_type' => ['required', Rule::in(Complaint::TYPES)],
            'subject' => ['required', 'string', 'max:255'],
            'complaint_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:5000'],
            'is_anonymous' => ['boolean'],
            'document' => Complaint::uploadRules(),
        ]);
        unset($data['document']);

        // Employees only file complaints as themselves: the complainant comes from the signed-in user.
        if (! $canActForOthers) {
            $data['employee_id'] = $complaint->employee_id ?? $user->employee()->value('id')
                ?? throw ValidationException::withMessages(['employee_id' => __('Your account has no employee profile.')]);
        }

        if ((int) ($data['against_employee_id'] ?? 0) === (int) $data['employee_id']) {
            throw ValidationException::withMessages(['against_employee_id' => __('A complaint cannot be filed against the complainant.')]);
        }

        return $data;
    }
}
