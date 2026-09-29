<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\ContractType;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContractTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ContractType::query()
            ->visibleTo($request->user())
            ->withCount('contracts')
            ->when(in_array($request->input('is_renewable'), ['yes', 'no'], true), fn ($q) => $q->where('is_renewable', $request->input('is_renewable') === 'yes'));

        $counts = TableQuery::countBy($query, 'status');
        $query->when(in_array($request->input('status'), ContractType::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('hr/contracts/contract-types/index', [
            'contractTypes' => TableQuery::paginate($query, $request, ['name', 'description'], ['name', 'created_at']),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(ContractType::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['status', 'is_renewable']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ContractType::create($this->validated($request));

        return $this->done(__('Contract type created successfully.'));
    }

    public function update(Request $request, ContractType $contractType): RedirectResponse
    {
        abort_unless($contractType->isVisibleTo($request->user()), 403);
        $contractType->update($this->validated($request));

        return $this->done(__('Contract type updated successfully.'));
    }

    /**
     * The lock action: switch the contract type on or off.
     */
    public function toggleStatus(Request $request, ContractType $contractType): RedirectResponse
    {
        abort_unless($contractType->isVisibleTo($request->user()), 403);
        $contractType->update(['status' => $contractType->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Contract type status updated.'));
    }

    public function destroy(Request $request, ContractType $contractType): RedirectResponse
    {
        abort_unless($contractType->isVisibleTo($request->user()), 403);
        $contractType->delete();

        return $this->done(__('Contract type deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_duration_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'probation_period_months' => ['required', 'integer', 'min:0', 'max:60'],
            'notice_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_renewable' => ['boolean'],
            'status' => ['required', Rule::in(ContractType::STATUSES)],
        ]);
    }
}
