<?php

namespace Database\Seeders\Modules;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class OrgChartSeeder extends Seeder
{
    /**
     * The demo's reporting tree: adds the managers, HR staff and employees the employee
     * list doesn't include, then links everyone to who they report to.
     */
    public function run(): void
    {
        /** @var list<array{name: string, email: string, role: string, reports_to: string|null, employee_id: string|null, designation: string|null, department: string|null, branch: string|null}> $people */
        $people = File::json(database_path('demo/org-chart.json'), JSON_THROW_ON_ERROR);
        $users = [];

        foreach ($people as $person) {
            $user = User::query()->firstOrCreate(
                ['email' => $person['email']],
                ['name' => $person['name'], 'password' => 'Zx123456', 'email_verified_at' => now()],
            );
            $users[$person['email']] = $user;

            if (! $user->hasRole($person['role'])) {
                $user->assignRole($person['role']);
            }

            if ($person['role'] === 'employee' && $person['employee_id'] && ! $user->employee()->exists()) {
                $this->createProfile($user, $person);
            }
        }

        foreach ($people as $person) {
            $users[$person['email']]->update(['reports_to_id' => $person['reports_to'] ? $users[$person['reports_to']]->id : null]);
        }
    }

    /**
     * @param  array{employee_id: string|null, designation: string|null, department: string|null, branch: string|null, id_type?: string|null, id_number?: string|null}  $person
     */
    private function createProfile(User $user, array $person): void
    {
        $branch = Branch::query()->where('name', $person['branch'])->first();
        $department = Department::query()->where('name', $person['department'])
            ->orderByRaw('branch_id = ? desc', [$branch->id ?? 0])->first();
        $designation = $department && $person['designation']
            ? Designation::query()->firstOrCreate(
                ['department_id' => $department->id, 'name' => $person['designation']],
                ['status' => 'active'],
            )
            : null;

        Employee::query()->create([
            'user_id' => $user->id,
            'employee_id' => $person['employee_id'],
            // A department belongs to one branch; keep the placement consistent with it.
            'branch_id' => $department->branch_id ?? $branch?->id,
            'department_id' => $department?->id,
            'designation_id' => $designation?->id,
            'date_of_joining' => now()->toDateString(),
            'employment_type' => 'Full-time',
            'employee_status' => 'active',
            'id_type' => $person['id_type'] ?? null,
            'id_number' => $person['id_number'] ?? null,
        ]);
    }
}
