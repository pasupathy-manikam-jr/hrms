<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationChartController extends Controller
{
    /**
     * The reporting hierarchy of everyone in the company (users linked by reports_to_id),
     * shaped like the demo's chartData.
     */
    public function index(): Response
    {
        $users = User::query()
            ->with(['roles:id,name', 'employee.branch:id,name', 'employee.department:id,name', 'employee.designation:id,name'])
            ->orderBy('id') // creation order, like the demo
            ->get();

        $children = $users->groupBy(fn (User $user) => $user->reports_to_id ?? 0);
        $ids = $users->modelKeys();

        // Roots: nobody above them, or a manager who no longer exists.
        $roots = $users->filter(fn (User $user) => $user->reports_to_id === null || ! in_array($user->reports_to_id, $ids, true));
        $visited = [];
        $tree = $roots->map(fn (User $user) => $this->node($user, $children, $visited))->values();

        return Inertia::render('hr/organization-chart/index', [
            // The demo shows a single company root; several roots only happen with incomplete data.
            'chartData' => $tree->count() === 1 ? $tree->first() : $tree->all(),
            'totalCount' => max($users->count() - 1, 0),
        ]);
    }

    /**
     * @param  Collection<int|string, \Illuminate\Database\Eloquent\Collection<int, User>>  $children
     * @param  array<int, true>  $visited  guards against cycles in bad data
     * @return array<string, mixed>
     */
    private function node(User $user, Collection $children, array &$visited): array
    {
        $visited[$user->id] = true;
        $employee = $user->employee;
        $role = $user->getRoleNames()->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'designation' => $employee->designation->name ?? ($role ? ucfirst($role) : null),
            'department' => $employee?->department?->name,
            'branch' => $employee?->branch?->name,
            'employee_id' => $employee?->employee_id,
            'status' => $employee?->employee_status === 'terminated' || $user->status === 'inactive' ? 'inactive' : 'active',
            'children' => ($children->get($user->id) ?? collect())
                ->reject(fn (User $child) => isset($visited[$child->id]))
                ->map(fn (User $child) => $this->node($child, $children, $visited))
                ->values()
                ->all(),
        ];
    }
}
