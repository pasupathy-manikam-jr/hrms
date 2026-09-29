<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TripController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        $query = Trip::query()
            ->visibleTo($user)
            ->with('employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path', 'approver:id,name')
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->date('date_from'), fn ($q, $date) => $q->whereDate('start_date', '>=', $date))
            ->when($request->date('date_to'), fn ($q, $date) => $q->whereDate('end_date', '<=', $date))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('purpose', 'like', "%{$search}%")
                ->orWhere('destination', 'like', "%{$search}%")
                ->orWhereHas('employee.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))));

        $counts = TableQuery::countBy($query, 'status');

        $query->when(in_array($request->input('status'), Trip::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/trips/index', [
            'trips' => TableQuery::paginate($query, $request, [], ['start_date', 'end_date', 'status', 'total_expenses', 'created_at']),
            'employees' => Trip::employeeOptions($user),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(Trip::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all(),
            'advanceStatuses' => Trip::ADVANCE_STATUSES,
            'reimbursementStatuses' => Trip::REIMBURSEMENT_STATUSES,
            'filters' => TableQuery::filters($request, ['status', 'employee_id', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        (new Trip($this->validated($request)))->attachUploadFrom($request)->save();

        return $this->done(__('Trip created successfully.'));
    }

    public function update(Request $request, Trip $trip): RedirectResponse
    {
        abort_unless($trip->isVisibleTo($request->user()), 404);

        $trip->fill($this->validated($request))->attachUploadFrom($request)->save();

        return $this->done(__('Trip updated successfully.'));
    }

    public function destroy(Request $request, Trip $trip): RedirectResponse
    {
        abort_unless($trip->isVisibleTo($request->user()), 404);

        $trip->delete();

        return $this->done(__('Trip deleted successfully.'));
    }

    /**
     * Planned -> ongoing -> completed (or cancelled), recording who decided.
     */
    public function changeStatus(Request $request, Trip $trip): RedirectResponse
    {
        abort_unless($trip->isVisibleTo($request->user()), 404);
        $data = $request->validate(['status' => ['required', Rule::in(Trip::STATUSES)]]);

        $trip->update([...$data, 'approved_by' => $request->user()?->id, 'approved_at' => now()]);

        return $this->done(__('Trip status updated.'));
    }

    /**
     * The travel advance: travellers request one; only expense approvers can approve, pay or reconcile it.
     */
    public function advance(Request $request, Trip $trip): RedirectResponse
    {
        $data = $request->validate([
            'advance_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'advance_status' => ['required', Rule::in(Trip::ADVANCE_STATUSES)],
        ]);
        $this->authorizeFinance($request, $trip, $data['advance_status'] !== 'requested');

        $trip->update($data);

        return $this->done(__('Travel advance updated.'));
    }

    /**
     * The expense claim: travellers submit it as pending; only expense approvers can decide or pay it.
     */
    public function expenses(Request $request, Trip $trip): RedirectResponse
    {
        $data = $request->validate([
            'total_expenses' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'reimbursement_status' => ['required', Rule::in(Trip::REIMBURSEMENT_STATUSES)],
        ]);
        $this->authorizeFinance($request, $trip, $data['reimbursement_status'] !== 'pending');

        $trip->update($data);

        return $this->done(__('Trip expenses updated.'));
    }

    /**
     * The traveller's post-trip report.
     */
    public function report(Request $request, Trip $trip): RedirectResponse
    {
        abort_unless($trip->isVisibleTo($request->user()), 404);
        $data = $request->validate(['trip_report' => ['required', 'string', 'max:5000']]);

        $trip->update($data);

        return $this->done(__('Trip report saved.'));
    }

    public function document(Request $request, Trip $trip): StreamedResponse
    {
        abort_unless($trip->isVisibleTo($request->user()), 404);

        return $trip->downloadUpload();
    }

    private function authorizeFinance(Request $request, Trip $trip, bool $isDecision): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($trip->isVisibleTo($user), 404);
        abort_if($trip->status === 'cancelled', 403, __('Cancelled trips have no advance or expenses.'));
        abort_if($isDecision && ! $user->can('approve-trip-expenses'), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'purpose' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'expected_outcomes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(Trip::STATUSES)],
            'advance_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'total_expenses' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'document' => Trip::uploadRules(),
        ]);
        unset($data['document']);

        return $data;
    }
}
