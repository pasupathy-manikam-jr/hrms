<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\SalaryComponent;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SalaryComponentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = SalaryComponent::query()
            ->when($request->input('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->input('calculation_type'), fn ($q, $type) => $q->where('calculation_type', $type))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status));

        return Inertia::render('hr/salary-components/index', [
            'salaryComponents' => TableQuery::paginate($query, $request, ['name', 'description'], ['created_at']),
            'filters' => TableQuery::filters($request, ['type', 'calculation_type', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        SalaryComponent::create($this->validated($request));

        return $this->done(__('Salary component created successfully.'));
    }

    public function update(Request $request, SalaryComponent $salaryComponent): RedirectResponse
    {
        $salaryComponent->update($this->validated($request));

        return $this->done(__('Salary component updated successfully.'));
    }

    /**
     * The lock action: switch the component on or off for salary calculations.
     */
    public function toggleStatus(SalaryComponent $salaryComponent): RedirectResponse
    {
        $salaryComponent->update(['status' => $salaryComponent->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Salary component status updated.'));
    }

    public function destroy(SalaryComponent $salaryComponent): RedirectResponse
    {
        $salaryComponent->delete();

        return $this->done(__('Salary component deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(SalaryComponent::TYPES)],
            'calculation_type' => ['required', Rule::in(SalaryComponent::CALCULATION_TYPES)],
            'default_amount' => ['required_if:calculation_type,fixed', 'nullable', 'decimal:0,2', 'min:0', 'max:9999999999999'],
            'percentage_of_basic' => ['required_if:calculation_type,percentage', 'nullable', 'decimal:0,2', 'min:0', 'max:100'],
            'is_taxable' => ['boolean'],
            'is_mandatory' => ['boolean'],
            'status' => ['required', Rule::in(SalaryComponent::STATUSES)],
        ]);

        // Keep only the figure the calculation type uses.
        return $data['calculation_type'] === 'fixed'
            ? [...$data, 'percentage_of_basic' => null]
            : [...$data, 'default_amount' => 0];
    }
}
