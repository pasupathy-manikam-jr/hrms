<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeDocument;
use App\Models\EmployeeTraining;
use App\Models\Shift;
use App\Models\User;
use App\Support\Csv;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    private const USER_FIELDS = ['name', 'email', 'password'];

    public function index(Request $request): Response
    {
        $query = $this->filteredQuery($request);
        $counts = TableQuery::countBy($query, 'employee_status');
        $this->applySearch($query, $request);

        return Inertia::render('hr/employees/index', [
            'employees' => TableQuery::paginate($query, $request, [], ['employee_id', 'date_of_joining', 'created_at']),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name', 'branch_id']),
            'designations' => Designation::query()->orderBy('name')->get(['id', 'name', 'department_id']),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(Employee::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['branch', 'department', 'designation', 'status']),
        ]);
    }

    /**
     * The demo's five-step "Create Employee" wizard.
     */
    public function create(): Response
    {
        return Inertia::render('hr/employees/form', [...$this->formOptions(), 'employee' => null]);
    }

    public function edit(Request $request, Employee $employee): Response
    {
        $this->authorizeRecord($request, $employee);

        return Inertia::render('hr/employees/form', [
            ...$this->formOptions(),
            'employee' => $employee->load('user:id,name,email,reports_to_id,avatar_path', 'documents.documentType:id,name'),
        ]);
    }

    /**
     * Download one of an employee's uploaded documents.
     */
    public function document(Request $request, Employee $employee, EmployeeDocument $document): StreamedResponse
    {
        $this->authorizeRecord($request, $employee);
        abort_unless($document->employee_id === $employee->id, 404);

        return $document->downloadUpload();
    }

    /**
     * Lists the wizard's dropdowns and Documents step need.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name', 'branch_id']),
            'designations' => Designation::query()->orderBy('name')->get(['id', 'name', 'department_id']),
            'shifts' => Shift::query()->orderBy('name')->get(['id', 'name']),
            'managers' => User::query()->orderBy('name')->get(['id', 'name']),
            'documentTypes' => DocumentType::query()->orderBy('id')->get(['id', 'name', 'is_required']),
            'nextEmployeeId' => Employee::nextCode(),
        ];
    }

    /**
     * The employee's profile page (the demo's employees.show): basics, employment, contact,
     * banking, certifications (completed trainings) and documents (contracts).
     */
    public function show(Request $request, Employee $employee): Response
    {
        $this->authorizeRecord($request, $employee);

        $employee->load([
            'user:id,name,email,reports_to_id,avatar_path',
            'user.reportsTo:id,name',
            'branch:id,name', 'department:id,name', 'designation:id,name', 'shift:id,name,start_time,end_time',
            'documents.documentType:id,name',
        ]);

        return Inertia::render('hr/employees/show', [
            'employee' => $employee,
            'certifications' => EmployeeTraining::query()->with('program:id,name')
                ->where('employee_id', $employee->id)->where('status', 'completed')
                ->latest('completion_date')->get(['id', 'training_program_id', 'completion_date', 'score', 'certification']),
            'contracts' => EmployeeContract::query()->with('contractType:id,name')
                ->where('employee_id', $employee->id)->latest('start_date')
                ->get(['id', 'contract_number', 'contract_type_id', 'start_date', 'end_date', 'status']),
        ]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $data = $request->employeeData();

        // New employees report to whoever adds them unless a manager is chosen (the demo's behaviour).
        $data['reports_to_id'] = ($data['reports_to_id'] ?? null) ?: $request->user()?->id;

        DB::transaction(function () use ($data, $request) {
            $employee = $this->createEmployee($data);
            $this->saveUploads($request, $employee);
        });

        return $this->backToIndex(__('Employee created successfully.'));
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $data = $request->employeeData();

        $manager = isset($data['reports_to_id']) ? User::query()->whereKey($data['reports_to_id'])->first() : null;

        if ($manager && $employee->user->isOrManages($manager)) {
            throw ValidationException::withMessages(['reports_to_id' => __('An employee cannot report to themselves or to someone who reports to them.')]);
        }

        DB::transaction(function () use ($data, $employee) {
            $employee->user->update([
                ...array_filter(Arr::only($data, self::USER_FIELDS), fn ($value) => filled($value)),
                'reports_to_id' => $data['reports_to_id'] ?? null,
            ]);
            $employee->update([
                ...Arr::except($data, [...self::USER_FIELDS, 'reports_to_id']),
                'employee_id' => ($data['employee_id'] ?? null) ?: $employee->employee_id,
            ]);
        });
        $this->saveUploads($request, $employee);

        return $this->backToIndex(__('Employee updated successfully.'));
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorizeRecord($request, $employee);
        abort_if($employee->user_id === $request->user()?->id, 403, __('You cannot delete your own account here.'));

        // Deleting the user cascades to the employee row.
        DB::transaction(fn () => $employee->user->delete());

        return $this->done(__('Employee deleted successfully.'));
    }

    /** CSV columns: header => employee field (shared by export, template and import). */
    private const CSV_COLUMNS = [
        'Employee ID' => 'employee_id',
        'Name' => 'name',
        'Email' => 'email',
        'Password' => 'password',
        'Phone' => 'phone',
        'Gender' => 'gender',
        'Date of Birth' => 'date_of_birth',
        'ID Type' => 'id_type',
        'MyKad / Passport No.' => 'id_number',
        'Branch' => 'branch',
        'Department' => 'department',
        'Designation' => 'designation',
        'Shift' => 'shift',
        'Date of Joining' => 'date_of_joining',
        'Employment Type' => 'employment_type',
        'Status' => 'employee_status',
    ];

    public function export(Request $request): StreamedResponse
    {
        $employees = $this->applySearch($this->filteredQuery($request), $request)->orderBy('employee_id')->lazy();

        return Csv::download('employees-'.now()->format('Y-m-d').'.csv', array_keys(Arr::except(self::CSV_COLUMNS, 'Password')), $employees->map(fn (Employee $e) => [
            $e->employee_id, $e->user->name, $e->user->email, $e->phone, $e->gender, $e->date_of_birth?->toDateString(),
            $e->id_type, $e->id_number, $e->branch?->name, $e->department?->name, $e->designation?->name, $e->shift?->name,
            $e->date_of_joining?->toDateString(), $e->employment_type, $e->employee_status,
        ]));
    }

    public function template(): StreamedResponse
    {
        $sample = Employee::query()->with('branch', 'department', 'designation', 'shift')->whereNotNull('designation_id')->first();

        return Csv::download('employees-import-template.csv', array_keys(self::CSV_COLUMNS), [[
            '', 'Nur Aisyah binti Hassan', 'aisyah.hassan@example.com', '', '+60 12-345 6789', 'female', '1995-04-12',
            'mykad', '950412-14-5678', $sample?->branch?->name, $sample?->department?->name, $sample?->designation?->name, $sample?->shift?->name,
            now()->toDateString(), 'Full-time', 'active',
        ]]);
    }

    /**
     * Import employees from the template's CSV. Each row goes through the same rules and
     * creation as the form; bad rows are skipped and reported with their row number.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => Csv::uploadRules()]);

        $branches = Branch::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);
        $shifts = Shift::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);
        $imported = 0;
        $skipped = [];

        foreach (Csv::read($request->file('file')) as $line => $row) {
            $input = collect(self::CSV_COLUMNS)->mapWithKeys(fn ($field, $header) => [$field => $row[Str::snake($header)] ?? ''])->all();
            $branchId = $branches[mb_strtolower($input['branch'])] ?? null;
            $departmentId = $branchId ? Department::query()->where('branch_id', $branchId)->where('name', $input['department'])->value('id') : null;

            $input = [
                ...Arr::except($input, ['branch', 'department', 'designation', 'shift']),
                'password' => $input['password'] ?: Str::password(16),
                'employment_type' => $input['employment_type'] ?: 'Full-time',
                'employee_status' => mb_strtolower($input['employee_status']) ?: 'active',
                'gender' => mb_strtolower($input['gender']) ?: null,
                'id_type' => mb_strtolower($input['id_type']) ?: null,
                'branch_id' => $branchId,
                'department_id' => $departmentId,
                'designation_id' => $departmentId ? Designation::query()->where('department_id', $departmentId)->where('name', $input['designation'])->value('id') : null,
                'shift_id' => $shifts[mb_strtolower($input['shift'])] ?? null,
            ];

            $input['id_number'] = EmployeeRequest::normalizeIdNumber($input);
            $validator = Validator::make(array_map(fn ($value) => $value === '' ? null : $value, $input), EmployeeRequest::rulesFor($input), [], EmployeeRequest::attributeNames());

            if ($validator->fails()) {
                $skipped[] = ['row' => $line, 'errors' => $validator->errors()->all()];

                continue;
            }

            $this->createEmployee([...$validator->validated(), 'reports_to_id' => $request->user()?->id]);
            $imported++;
        }

        Inertia::flash('import', ['imported' => $imported, 'skipped' => $skipped]);

        return $skipped === []
            ? $this->done(__(':count employees imported.', ['count' => $imported]))
            : $this->toast('error', __(':imported imported, :skipped skipped. See the import report.', ['imported' => $imported, 'skipped' => count($skipped)]));
    }

    /**
     * Create the login and the employee profile together.
     *
     * @param  array<string, mixed>  $data  validated input, with reports_to_id resolved
     */
    private function createEmployee(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            $user = new User([...Arr::only($data, self::USER_FIELDS), 'reports_to_id' => $data['reports_to_id']]);
            // Accounts created by HR are trusted, so they can sign in straight away.
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole('employee');

            return $user->employee()->create([
                ...Arr::except($data, [...self::USER_FIELDS, 'reports_to_id']),
                'employee_id' => ($data['employee_id'] ?? null) ?: Employee::nextCode(),
            ]);
        });
    }

    private function backToIndex(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('hr.employees.index');
    }

    /**
     * The wizard's profile photo and documents (validated in validated()), replacing earlier files.
     */
    private function saveUploads(Request $request, Employee $employee): void
    {
        $employee->user->replaceAvatar($request->file('photo'));

        foreach ((array) $request->file('documents', []) as $typeId => $file) {
            $document = EmployeeDocument::query()->firstOrNew(['employee_id' => $employee->id, 'document_type_id' => (int) $typeId]);
            $document->attachUpload($file)->save();
        }
    }

    /**
     * The list's visible, filtered employees (branch/department/designation), before search and status.
     *
     * @return Builder<Employee>
     */
    private function filteredQuery(Request $request): Builder
    {
        /** @var User $user */
        $user = $request->user();

        return Employee::query()
            ->visibleTo($user)
            ->with('user:id,name,email,reports_to_id,avatar_path', 'branch:id,name', 'department:id,name', 'designation:id,name', 'shift:id,name')
            ->when($request->integer('branch'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->integer('department'), fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->integer('designation'), fn ($q, $id) => $q->where('designation_id', $id));
    }

    /**
     * Search and status tab on top of filteredQuery (status counts are taken before this).
     *
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    private function applySearch(Builder $query, Request $request): Builder
    {
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');

        return $query
            ->when($request->input('status'), fn ($q, $status) => $q->where('employee_status', $status))
            // Name and email live on users, so search here; TableQuery gets no searchable columns.
            ->when($search !== '', fn ($q) => $q->where(fn (Builder $q) => $q
                ->where('employee_id', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))));
    }

    private function authorizeRecord(Request $request, Employee $employee): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(Employee::query()->visibleTo($user)->whereKey($employee->id)->exists(), 404);
    }
}
