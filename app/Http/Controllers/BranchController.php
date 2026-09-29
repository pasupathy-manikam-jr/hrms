<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('hr/branches/index', [
            'branches' => TableQuery::paginate(Branch::query(), $request, ['name', 'email', 'phone', 'city'], ['name', 'created_at']),
            'filters' => TableQuery::filters($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Branch::create($this->validated($request));

        return $this->done(__('Branch created successfully.'));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $branch->update($this->validated($request));

        return $this->done(__('Branch updated successfully.'));
    }

    public function toggleStatus(Branch $branch): RedirectResponse
    {
        $branch->update(['status' => $branch->status === 'active' ? 'inactive' : 'active']);

        return $this->done(__('Branch status updated.'));
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $branch->delete();

        return $this->done(__('Branch deleted successfully.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::in(Branch::STATUSES)],
        ]);
    }
}
