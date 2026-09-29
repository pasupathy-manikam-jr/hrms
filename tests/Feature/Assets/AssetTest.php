<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\AssetType;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Dell Latitude',
            'asset_type_id' => AssetType::factory()->create()->id,
            'asset_code' => 'COM100',
            'purchase_date' => '2025-01-15',
            'purchase_cost' => '85000.00',
            'salvage_value' => '8500.00',
            'useful_life_years' => 3,
            'status' => 'available',
            'condition' => 'new',
            ...$overrides,
        ];
    }

    private function employeeFor(User $user): Employee
    {
        return Employee::factory()->create(['user_id' => $user->id]);
    }

    public function test_assets_can_be_created_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.assets.store'), ['name' => '', 'asset_type_id' => 999, 'purchase_cost' => -1, 'status' => 'assigned', 'condition' => 'broken', 'useful_life_years' => 0])
            ->assertSessionHasErrors(['name', 'asset_type_id', 'purchase_cost', 'status', 'condition', 'useful_life_years']);
        $this->post(route('hr.assets.store'), $this->payload(['salvage_value' => '90000']))->assertSessionHasErrors('salvage_value');
        $this->post(route('hr.assets.store'), $this->payload(['salvage_value' => null]))->assertSessionHasNoErrors();

        $asset = Asset::where('asset_code', 'COM100')->firstOrFail();
        $this->assertSame('0.00', $asset->salvage_value);

        $this->post(route('hr.assets.store'), $this->payload(['name' => 'Duplicate']))->assertSessionHasErrors('asset_code');

        $this->put(route('hr.assets.update', $asset), $this->payload(['name' => 'Dell Latitude 7440', 'status' => 'under_maintenance']))->assertSessionHasNoErrors();
        $this->assertSame('Dell Latitude 7440', $asset->fresh()->name);
        $this->assertSame('under_maintenance', $asset->fresh()->status);

        $this->delete(route('hr.assets.destroy', $asset));
        $this->assertModelMissing($asset);
    }

    public function test_list_filters_by_type_and_derived_status_with_counts()
    {
        $laptops = AssetType::factory()->create();
        $assigned = Asset::factory()->create(['asset_type_id' => $laptops->id]);
        $assigned->assignTo(Employee::factory()->create(), '2026-01-01');
        Asset::factory()->create(['asset_type_id' => $laptops->id]);
        Asset::factory()->create(['status' => 'disposed']);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.assets.index', ['status' => 'assigned']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/assets/index')
                ->has('assets.data', 1)
                ->where('assets.data.0.id', $assigned->id)
                ->where('assets.data.0.status', 'assigned')
                ->where('statusCounts', ['all' => 3, 'available' => 1, 'assigned' => 1, 'under_maintenance' => 0, 'disposed' => 1]));

        $this->get(route('hr.assets.index', ['asset_type_id' => $laptops->id, 'status' => 'available']))
            ->assertInertia(fn ($page) => $page
                ->has('assets.data', 1)
                ->where('statusCounts.all', 2)
                ->where('statusCounts.disposed', 0));
    }

    public function test_assign_and_return_keep_status_in_line_with_history()
    {
        $this->actingAs($this->userWithRole());
        $asset = Asset::factory()->create();
        $employee = Employee::factory()->create();

        $this->post(route('hr.assets.assign', $asset), ['employee_id' => $employee->id, 'assigned_at' => '2026-02-01'])->assertSessionHasNoErrors();
        $this->assertSame('assigned', $asset->fresh()->status);

        // Already assigned: a second checkout and a status change are refused.
        $this->post(route('hr.assets.assign', $asset), ['employee_id' => Employee::factory()->create()->id, 'assigned_at' => '2026-02-02'])->assertSessionHasErrors('employee_id');
        $this->put(route('hr.assets.update', $asset), [...$asset->only(['name', 'asset_type_id', 'purchase_cost', 'useful_life_years', 'condition']), 'status' => 'disposed'])->assertSessionHasErrors('status');

        $this->post(route('hr.assets.return', $asset), ['returned_at' => '2026-01-01'])->assertSessionHasErrors('returned_at');
        $this->post(route('hr.assets.return', $asset), ['returned_at' => '2026-03-01', 'notes' => 'Returned in good shape'])->assertSessionHasNoErrors();

        $assignment = $asset->assignments()->sole();
        $this->assertSame('2026-03-01', $assignment->returned_at->toDateString());
        $this->assertSame('available', $asset->fresh()->status);

        // Nothing open any more.
        $this->post(route('hr.assets.return', $asset), ['returned_at' => '2026-03-02'])->assertSessionHasErrors('returned_at');
    }

    public function test_disposed_or_maintenance_assets_cannot_be_assigned()
    {
        $this->actingAs($this->userWithRole());
        $employee = Employee::factory()->create();

        foreach (['disposed', 'under_maintenance'] as $status) {
            $asset = Asset::factory()->create(['status' => $status]);
            $this->post(route('hr.assets.assign', $asset), ['employee_id' => $employee->id, 'assigned_at' => '2026-02-01'])->assertSessionHasErrors('employee_id');
            $this->assertSame(0, $asset->assignments()->count());
        }
    }

    public function test_book_value_is_straight_line_per_full_month_down_to_salvage()
    {
        $asset = Asset::factory()->make(['purchase_date' => '2026-01-15', 'purchase_cost' => 12000, 'salvage_value' => 0, 'useful_life_years' => 1]);

        $this->assertSame(12000.0, $asset->bookValue(CarbonImmutable::parse('2025-12-01')));
        $this->assertSame(10000.0, $asset->bookValue(CarbonImmutable::parse('2026-04-14')));
        $this->assertSame(9000.0, $asset->bookValue(CarbonImmutable::parse('2026-04-15')));
        $this->assertSame(0.0, $asset->bookValue(CarbonImmutable::parse('2030-01-01')));

        // The demo's Dell OptiPlex: 85,000 cost, 8,500 salvage, 3 years; one year on it is worth 59,500.
        $dell = Asset::factory()->make(['purchase_date' => '2025-01-15', 'purchase_cost' => 85000, 'salvage_value' => 8500, 'useful_life_years' => 3]);
        $this->assertSame(59500.0, $dell->bookValue(CarbonImmutable::parse('2026-01-15')));
        $this->assertSame(8500.0, $dell->bookValue(CarbonImmutable::parse('2035-01-01')));

        $this->assertSame(500.0, Asset::factory()->make(['purchase_date' => null, 'purchase_cost' => 500])->bookValue());
    }

    public function test_status_stats_use_the_demo_labels_and_colours()
    {
        Asset::factory()->count(2)->create();
        Asset::factory()->create()->assignTo(Employee::factory()->create(), '2026-01-01');
        Asset::factory()->create(['status' => 'under_maintenance']);

        $this->assertSame([
            ['name' => 'Available', 'value' => 2, 'color' => '#10B981'],
            ['name' => 'Assigned', 'value' => 1, 'color' => '#3B82F6'],
            ['name' => 'Maintenance', 'value' => 1, 'color' => '#F59E0B'],
            ['name' => 'Disposed', 'value' => 0, 'color' => '#EF4444'],
        ], Asset::statusStats());
    }

    public function test_employees_only_see_assets_assigned_to_them()
    {
        $user = $this->userWithRole('employee');
        $mine = Asset::factory()->create();
        $mine->assignTo($this->employeeFor($user), '2026-01-01');
        Asset::factory()->create()->assignTo(Employee::factory()->create(), '2026-01-01');
        Asset::factory()->create();

        $this->actingAs($user)
            ->get(route('hr.assets.index'))
            ->assertInertia(fn ($page) => $page
                ->has('assets.data', 1)
                ->where('assets.data.0.id', $mine->id)
                ->where('statusCounts.all', 1)
                ->where('employees', []));

        $this->get(route('hr.assets.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('totals.assets', 1)
                ->has('recentAssignments', 1));

        $this->get(route('hr.assets.depreciation-report'))
            ->assertInertia(fn ($page) => $page->has('assets.data', 1));
    }

    public function test_employees_cannot_change_or_assign_assets()
    {
        $asset = Asset::factory()->create();
        $employee = Employee::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->post(route('hr.assets.store'), $this->payload())->assertForbidden();
        $this->put(route('hr.assets.update', $asset), $this->payload())->assertForbidden();
        $this->delete(route('hr.assets.destroy', $asset))->assertForbidden();
        $this->post(route('hr.assets.assign', $asset), ['employee_id' => $employee->id, 'assigned_at' => '2026-01-01'])->assertForbidden();
        $this->post(route('hr.assets.return', $asset), ['returned_at' => '2026-01-01'])->assertForbidden();
        $this->assertModelExists($asset);
        $this->assertSame(0, $asset->assignments()->count());
    }

    public function test_dashboard_reports_counts_types_value_and_recent_assignments()
    {
        $type = AssetType::factory()->create(['name' => 'Laptops']);
        Asset::factory()->create(['asset_type_id' => $type->id, 'purchase_cost' => 1000, 'salvage_value' => 0, 'useful_life_years' => 5, 'purchase_date' => now()->toDateString()]);
        $assigned = Asset::factory()->create(['asset_type_id' => $type->id, 'purchase_cost' => 500, 'salvage_value' => 0, 'useful_life_years' => 5, 'purchase_date' => now()->toDateString()]);
        $assigned->assignTo(Employee::factory()->create(), now()->toDateString());
        AssetMaintenance::query()->create(['asset_id' => $assigned->id, 'maintenance_type' => 'preventive', 'start_date' => today()->addWeek(), 'end_date' => today()->addWeeks(2), 'details' => 'Service']);
        AssetMaintenance::query()->create(['asset_id' => $assigned->id, 'maintenance_type' => 'repair', 'start_date' => '2020-01-01', 'end_date' => '2020-01-02', 'details' => 'Old']);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.assets.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/assets/dashboard')
                ->where('statusStats.0.value', 1)
                ->where('statusStats.1.value', 1)
                ->where('typeStats.0', ['name' => 'Laptops', 'count' => 2])
                ->where('totals.assets', 2)
                ->where('totals.purchase_value', 1500)
                ->where('totals.current_value', 1500)
                ->where('totals.monthly_depreciation', 25)
                ->has('valueTrend', 12)
                ->where('valueTrend.11.value', 1500)
                ->has('maintenance', 1)
                ->where('maintenance.0.status', 'upcoming')
                ->has('recentAssets', 2)
                ->has('recentAssignments', 1)
                ->has('recentAssignments.0.employee.user.name')
                ->missing('recentAssignments.0.employee.account_number'));
    }

    public function test_depreciation_report_totals_follow_the_type_filter()
    {
        $vehicles = AssetType::factory()->create();
        Asset::factory()->create(['asset_type_id' => $vehicles->id, 'purchase_date' => now()->subYear()->toDateString(), 'purchase_cost' => 12000, 'salvage_value' => 0, 'useful_life_years' => 2]);
        Asset::factory()->create(['purchase_cost' => 99999]);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.assets.depreciation-report', ['asset_type_id' => $vehicles->id]))
            ->assertInertia(fn ($page) => $page
                ->component('hr/assets/depreciation-report')
                ->has('assets.data', 1)
                ->where('assets.data.0.current_value', 6000)
                ->where('totals', ['purchase_value' => 12000, 'current_value' => 6000, 'depreciation' => 6000]));
    }

    public function test_asset_types_list_shows_asset_counts()
    {
        $type = AssetType::factory()->create();
        Asset::factory()->count(2)->create(['asset_type_id' => $type->id]);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.asset-types.index'))
            ->assertInertia(fn ($page) => $page->where('assetTypes.data.0.assets_count', 2));
    }

    public function test_asset_page_shows_details_book_value_and_assignment_history()
    {
        $this->withoutVite();
        $asset = Asset::factory()->create(['purchase_date' => now()->subYear()->toDateString(), 'purchase_cost' => 1200, 'salvage_value' => 0, 'useful_life_years' => 2]);
        $employee = Employee::factory()->create();
        $asset->assignTo($employee, now()->subMonth()->toDateString());

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.assets.show', $asset))
            ->assertInertia(fn ($page) => $page
                ->component('hr/assets/show')
                ->where('asset.id', $asset->id)
                ->where('asset.status', 'assigned')
                ->where('asset.current_value', 600)
                ->where('asset.assignments.0.employee.user.name', $employee->user->name)
                ->has('employees'));
    }

    public function test_employees_can_only_open_assets_assigned_to_them()
    {
        $this->withoutVite();
        $user = $this->userWithRole('employee');
        $own = Asset::factory()->create();
        $own->assignTo(Employee::factory()->create(['user_id' => $user->id]), '2026-01-01');
        $other = Asset::factory()->create();

        $this->actingAs($user)->get(route('hr.assets.show', $own))
            ->assertInertia(fn ($page) => $page->where('employees', []));
        $this->get(route('hr.assets.show', $other))->assertNotFound();
    }
}
