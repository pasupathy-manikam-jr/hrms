<?php

namespace App\Http\Controllers;

use App\Models\Resignation;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResignationController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        $query = Resignation::query()
            ->visibleTo($user)
            ->with('employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path', 'approver:id,name')
            ->when($request->integer('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->date('date_from'), fn ($q, $date) => $q->whereDate('resignation_date', '>=', $date))
            ->when($request->date('date_to'), fn ($q, $date) => $q->whereDate('resignation_date', '<=', $date))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('reason', 'like', "%{$search}%")
                ->orWhereHas('employee.user', fn ($u) => $u->where('name', 'like', "%{$search}%"))));

        $counts = TableQuery::countBy($query, 'status');

        $query->when(in_array($request->input('status'), Resignation::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/resignations/index', [
            'resignations' => TableQuery::paginate($query, $request, [], ['resignation_date', 'last_working_day', 'status', 'created_at']),
            'employees' => Resignation::employeeOptions($user),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(Resignation::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['status', 'employee_id', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        (new Resignation([...$this->validated($request), 'status' => 'pending']))->attachUploadFrom($request)->save();

        return $this->done(__('Resignation submitted successfully.'));
    }

    public function update(Request $request, Resignation $resignation): RedirectResponse
    {
        $this->authorizeChange($request, $resignation);

        $resignation->fill($this->validated($request, $resignation))->attachUploadFrom($request)->save();

        return $this->done(__('Resignation updated successfully.'));
    }

    public function destroy(Request $request, Resignation $resignation): RedirectResponse
    {
        $this->authorizeChange($request, $resignation);

        $resignation->delete();

        return $this->done(__('Resignation deleted successfully.'));
    }

    /**
     * Approve, reject or complete a resignation; each needs the matching demo permission.
     */
    /**
     * Stream the resignation letter to someone allowed to see the resignation.
     */
    public function document(Request $request, Resignation $resignation): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($resignation->isVisibleTo($user), 404);

        return $resignation->downloadUpload();
    }

    public function changeStatus(Request $request, Resignation $resignation): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($resignation->isVisibleTo($user), 404);

        $status = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected', 'completed'])]])['status'];
        abort_unless($user->can($status === 'rejected' ? 'reject-resignations' : 'approve-resignations'), 403);

        $resignation->update(['status' => $status, 'approved_by' => $user->id, 'approved_at' => now()]);

        return $this->done(__('Resignation status updated.'));
    }

    /**
     * Staff may change any visible record; employees only their own pending ones.
     */
    private function authorizeChange(Request $request, Resignation $resignation): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($resignation->isVisibleTo($user), 404);
        abort_unless($user->can('manage-any-resignations') || $resignation->status === 'pending', 403, __('Only pending resignations can be changed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Resignation $resignation = null): array
    {
        /** @var User $user */
        $user = $request->user();
        $canActForOthers = $user->can('manage-any-resignations');

        $data = $request->validate([
            'employee_id' => [Rule::requiredIf($canActForOthers), 'integer', Rule::exists('employees', 'id')],
            'resignation_date' => ['required', 'date_format:Y-m-d'],
            'last_working_day' => ['required', 'date_format:Y-m-d', 'after_or_equal:resignation_date'],
            'notice_period' => ['nullable', 'string', 'max:50'],
            'reason' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'document' => Resignation::uploadRules(),
        ]);
        unset($data['document']);

        // Employees only resign for themselves: the employee comes from the signed-in user.
        if (! $canActForOthers) {
            $data['employee_id'] = $resignation->employee_id ?? $user->employee()->value('id')
                ?? throw ValidationException::withMessages(['employee_id' => __('Your account has no employee profile.')]);
        }

        return $data;
    }
}
