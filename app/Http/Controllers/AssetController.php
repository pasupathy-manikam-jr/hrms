<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetMaintenance;
use App\Models\AssetType;
use App\Models\Employee;
use App\Models\User;
use App\Support\Csv;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    /** Assignee columns safe to send to the page (employees also hold bank details). */
    private const ASSIGNEE = ['employee:id,user_id,employee_id,gender', 'employee.user:id,name,email,avatar_path'];

    private const SEARCHABLE = ['name', 'serial_number', 'asset_code', 'location'];

    /** CSV header => form field, shared by the export, the template and the import. */
    private const CSV_COLUMNS = [
        'Name' => 'name',
        'Asset Type' => 'asset_type',
        'Serial Number' => 'serial_number',
        'Asset Code' => 'asset_code',
        'Purchase Date' => 'purchase_date',
        'Purchase Cost' => 'purchase_cost',
        'Salvage Value' => 'salvage_value',
        'Useful Life Years' => 'useful_life_years',
        'Status' => 'status',
        'Condition' => 'condition',
        'Location' => 'location',
        'Description' => 'description',
    ];

    public function index(Request $request): Response
    {
        $user = $this->user($request);

        $query = $this->filteredQuery($request)
            ->with([
                'assetType:id,name',
                'currentAssignment',
                'assignments' => fn ($q) => $q->with(self::ASSIGNEE)->latest('assigned_at')->latest('id'),
            ]);

        $counts = Asset::statusCounts($query);

        $query->when(in_array($request->input('status'), Asset::STATUSES, true), fn ($q) => $q->whereStatus($request->string('status')->toString()));

        return Inertia::render('hr/assets/index', [
            'assets' => TableQuery::paginate($query, $request, self::SEARCHABLE, ['name', 'purchase_date', 'purchase_cost', 'created_at']),
            'assetTypes' => AssetType::query()->orderBy('name')->get(['id', 'name']),
            'employees' => $user->can('assign-assets') ? $this->employeeOptions() : [],
            'statusCounts' => ['all' => array_sum($counts)] + $counts,
            'filters' => TableQuery::filters($request, ['asset_type_id', 'status', 'condition', 'date_from', 'date_to']),
        ]);
    }

    public function show(Request $request, Asset $asset): Response
    {
        $user = $this->user($request);
        abort_unless(Asset::query()->visibleTo($user)->whereKey($asset->id)->exists(), 404);

        return Inertia::render('hr/assets/show', [
            'asset' => $asset->load([
                'assetType:id,name',
                'currentAssignment',
                'assignments' => fn ($q) => $q->with(self::ASSIGNEE)->latest('assigned_at')->latest('id'),
            ]),
            'employees' => $user->can('assign-assets') ? $this->employeeOptions() : [],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');
        $assets = $this->filteredQuery($request)
            ->with(['assetType:id,name', 'currentAssignment.employee.user:id,name'])
            ->when(in_array($request->input('status'), Asset::STATUSES, true), fn ($q) => $q->whereStatus($request->string('status')->toString()))
            ->when($search, fn ($q) => $q->where(fn ($q) => collect(self::SEARCHABLE)->each(fn ($column) => $q->orWhere($column, 'like', "%{$search}%"))))
            ->orderBy('name')->lazy();

        return Csv::download('assets-'.now()->format('Y-m-d').'.csv', [...array_keys(self::CSV_COLUMNS), 'Assigned To', 'Current Value'], $assets->map(fn (Asset $a) => [
            $a->name, $a->assetType?->name, $a->serial_number, $a->asset_code, $a->purchase_date?->toDateString(), $a->purchase_cost, $a->salvage_value,
            $a->useful_life_years, $a->status, $a->condition, $a->location, $a->description,
            $a->currentAssignment?->employee?->user?->name, $a->current_value,
        ]));
    }

    public function template(): StreamedResponse
    {
        return Csv::download('assets-import-template.csv', array_keys(self::CSV_COLUMNS), [[
            'Dell Latitude 5440 Laptop', AssetType::query()->orderBy('name')->value('name') ?? 'IT Equipment', 'SN-12345678', 'LAP001',
            now()->subMonth()->toDateString(), '4500.00', '500.00', '4', 'available', 'new', 'Main Office', '',
        ]]);
    }

    /**
     * Import assets from the template's CSV. Each row goes through the form's rules; bad rows
     * are skipped and reported with their row number. Assignment is done from the list.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => Csv::uploadRules()]);

        $types = AssetType::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);
        $imported = 0;
        $skipped = [];

        foreach (Csv::read($request->file('file')) as $line => $row) {
            $input = collect(self::CSV_COLUMNS)->mapWithKeys(fn ($field, $header) => [$field => ($row[Str::snake($header)] ?? '') ?: null])->all();
            $input = [
                ...Arr::except($input, 'asset_type'),
                'asset_type_id' => $types[mb_strtolower((string) $input['asset_type'])] ?? null,
                'status' => str_replace(' ', '_', mb_strtolower((string) $input['status'])) ?: 'available',
                'condition' => mb_strtolower((string) $input['condition']) ?: 'good',
                'useful_life_years' => $input['useful_life_years'] ?? 5,
            ];

            $validator = Validator::make($input, $this->rules(), ['asset_type_id.required' => __('The asset type does not match an existing asset type.')]);

            if ($validator->fails()) {
                $skipped[] = ['row' => $line, 'errors' => $validator->errors()->all()];

                continue;
            }

            Asset::create(['salvage_value' => 0, ...array_filter($validator->validated(), fn ($value) => $value !== null)]);
            $imported++;
        }

        Inertia::flash('import', ['imported' => $imported, 'skipped' => $skipped]);

        return $skipped === []
            ? $this->done(__(':count assets imported.', ['count' => $imported]))
            : $this->toast('error', __(':imported imported, :skipped skipped. See the import report.', ['imported' => $imported, 'skipped' => count($skipped)]));
    }

    public function dashboard(Request $request): Response
    {
        $user = $this->user($request);
        $query = Asset::query()->visibleTo($user);
        // ponytail: book values are summed in PHP; move to SQL if asset counts reach the tens of thousands.
        $assets = (clone $query)->get(['id', 'purchase_date', 'purchase_cost', 'salvage_value', 'useful_life_years']);
        $purchase = round((float) $assets->sum('purchase_cost'), 2);
        $current = round($assets->sum(fn (Asset $asset) => $asset->bookValue()), 2);

        return Inertia::render('hr/assets/dashboard', [
            'statusStats' => Asset::statusStats($query),
            'typeStats' => AssetType::query()
                ->withCount(['assets' => fn ($q) => $q->visibleTo($user)])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (AssetType $type) => ['name' => $type->name, 'count' => $type->assets_count]),
            'totals' => [
                'assets' => $assets->count(),
                'purchase_value' => $purchase,
                'current_value' => $current,
                'depreciation' => round($purchase - $current, 2),
                // This month's charge: each asset still above its salvage value loses (cost - salvage) / life in months.
                'monthly_depreciation' => round($assets->sum(fn (Asset $asset) => $asset->useful_life_years > 0 && $asset->bookValue() > (float) $asset->salvage_value
                    ? ((float) $asset->purchase_cost - (float) $asset->salvage_value) / ($asset->useful_life_years * 12)
                    : 0), 2),
            ],
            // Total book value at each of the last 12 month ends.
            'valueTrend' => collect(range(11, 0))->map(function (int $ago) use ($assets) {
                $monthEnd = now()->startOfMonth()->subMonths($ago)->endOfMonth();

                return [
                    'month' => $monthEnd->format('M'),
                    'value' => round($assets->filter(fn (Asset $asset) => $asset->purchase_date === null || $asset->purchase_date->lte($monthEnd))
                        ->sum(fn (Asset $asset) => $asset->bookValue($monthEnd)), 2),
                ];
            }),
            'recentAssignments' => AssetAssignment::query()
                ->with(['asset:id,name,asset_code', ...self::ASSIGNEE])
                ->when(! $user->can('manage-any-assets'), fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('user_id', $user->id)))
                ->latest('assigned_at')->latest('id')
                ->limit(5)
                ->get(),
            'maintenance' => AssetMaintenance::query()
                ->whereIn('asset_id', (clone $query)->select('assets.id'))
                ->whereDate('end_date', '>=', today())
                ->with('asset:id,name')
                ->orderBy('start_date')->orderBy('id')
                ->limit(5)
                ->get(),
            'recentAssets' => (clone $query)
                ->with(['assetType:id,name', 'currentAssignment.employee:id,user_id,gender', 'currentAssignment.employee.user:id,name,email,avatar_path'])
                ->latest()->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function depreciationReport(Request $request): Response
    {
        $query = $this->filteredQuery($request);

        $all = (clone $query)->get(['id', 'purchase_date', 'purchase_cost', 'salvage_value', 'useful_life_years']);
        $purchase = round((float) $all->sum('purchase_cost'), 2);
        $current = round($all->sum(fn (Asset $asset) => $asset->bookValue()), 2);

        return Inertia::render('hr/assets/depreciation-report', [
            'assets' => TableQuery::paginate($query->with('assetType:id,name', 'currentAssignment'), $request, ['name', 'asset_code'], ['name', 'purchase_date', 'purchase_cost', 'created_at']),
            'assetTypes' => AssetType::query()->orderBy('name')->get(['id', 'name']),
            'totals' => ['purchase_value' => $purchase, 'current_value' => $current, 'depreciation' => round($purchase - $current, 2)],
            'filters' => TableQuery::filters($request, ['asset_type_id', 'date_from', 'date_to']),
        ]);
    }

    public function exportDepreciation(Request $request): StreamedResponse
    {
        $assets = $this->filteredQuery($request)->with('assetType:id,name')->orderBy('name')->lazy();

        return Csv::download('asset-depreciation-'.now()->format('Y-m-d').'.csv', ['Asset Name', 'Asset Type', 'Purchase Date', 'Purchase Cost', 'Depreciation Method', 'Current Value', 'Depreciation', 'Depreciation %'], $assets->map(function (Asset $a) {
            $cost = (float) $a->purchase_cost;
            $depreciation = round($cost - $a->bookValue(), 2);

            return [$a->name, $a->assetType?->name, $a->purchase_date?->toDateString(), $a->purchase_cost, 'Straight Line', round($a->bookValue(), 2), $depreciation, $cost > 0 ? round($depreciation / $cost * 100, 2) : 0];
        }));
    }

    public function store(Request $request): RedirectResponse
    {
        Asset::create($this->validated($request));

        return $this->done(__('Asset created successfully.'));
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $data = $this->validated($request, $asset);

        if ($asset->currentAssignment !== null && $data['status'] !== 'available') {
            throw ValidationException::withMessages(['status' => __('Return the asset before changing its status.')]);
        }

        $asset->update($data);

        return $this->done(__('Asset updated successfully.'));
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $asset->delete();

        return $this->done(__('Asset deleted successfully.'));
    }

    public function assign(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'assigned_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $asset->assignTo(Employee::query()->findOrFail($request->integer('employee_id')), $data['assigned_at'], $data['notes'] ?? null);

        return $this->done(__('Asset assigned successfully.'));
    }

    public function return(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate([
            'returned_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $asset->returnAsset($data['returned_at'], $data['notes'] ?? null);

        return $this->done(__('Asset returned successfully.'));
    }

    /**
     * Employees an asset can be assigned to, as "Name (EMP0001)".
     *
     * @return Collection<int, array{id: int, name: non-falsy-string}>
     */
    private function employeeOptions(): Collection
    {
        return Employee::query()->with('user:id,name')->get(['id', 'user_id', 'employee_id'])
            ->map(fn (Employee $employee) => ['id' => $employee->id, 'name' => $employee->user->name.' ('.$employee->employee_id.')'])
            ->sortBy('name')->values();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    /**
     * The list's filters (everything but search and the status tab).
     *
     * @return Builder<Asset>
     */
    private function filteredQuery(Request $request): Builder
    {
        return Asset::query()
            ->visibleTo($this->user($request))
            ->when($request->integer('asset_type_id'), fn ($q, $id) => $q->where('asset_type_id', $id))
            ->when(in_array($request->input('condition'), Asset::CONDITIONS, true), fn ($q) => $q->where('condition', $request->input('condition')))
            ->when($request->date('date_from'), fn ($q, $date) => $q->whereDate('purchase_date', '>=', $date))
            ->when($request->date('date_to'), fn ($q, $date) => $q->whereDate('purchase_date', '<=', $date));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Asset $asset = null): array
    {
        $data = $request->validate($this->rules($asset));

        return ['salvage_value' => $data['salvage_value'] ?? 0] + $data;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(?Asset $asset = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'asset_type_id' => ['required', 'integer', Rule::exists('asset_types', 'id')],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'asset_code' => ['nullable', 'string', 'max:50', Rule::unique('assets')->ignore($asset)],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_cost' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'salvage_value' => ['nullable', 'numeric', 'min:0', 'lte:purchase_cost'],
            'useful_life_years' => ['required', 'integer', 'min:1', 'max:50'],
            'status' => ['required', Rule::in(Asset::BASE_STATUSES)],
            'condition' => ['required', Rule::in(Asset::CONDITIONS)],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
