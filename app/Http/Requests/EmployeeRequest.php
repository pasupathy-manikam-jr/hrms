<?php

namespace App\Http\Requests;

use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The employee wizard's rules. As a form request it also answers Laravel Precognition, so the wizard's
 * "Next" button validates just the current step's fields on the server before moving on.
 */
class EmployeeRequest extends FormRequest
{
    /**
     * Route permissions guard access; this keeps editing to employees the user may see.
     */
    public function authorize(): bool
    {
        $employee = $this->employee();
        /** @var User $user */
        $user = $this->user();

        return $employee === null || Employee::query()->visibleTo($user)->whereKey($employee->id)->exists();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['id_number' => self::normalizeIdNumber($this->all())]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $employee = $this->employee();
        $rules = [
            ...self::rulesFor($this->all(), $employee),
            'photo' => ['nullable', 'image', 'max:2048'],
            'documents' => ['array'],
            'documents.*' => EmployeeDocument::uploadRules(),
        ];

        // Required document types must be uploaded, unless the employee already has that document.
        $existing = $employee?->documents()->pluck('document_type_id')->all() ?? [];
        foreach (DocumentType::query()->where('is_required', true)->whereNotIn('id', $existing)->pluck('id') as $typeId) {
            $rules["documents.{$typeId}"] = EmployeeDocument::uploadRules(required: true);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...self::attributeNames(),
            ...DocumentType::query()->pluck('name', 'id')->mapWithKeys(fn ($name, $id) => ["documents.{$id}" => $name])->all(),
        ];
    }

    /**
     * The employee's fields without the uploads, ready to save.
     *
     * @return array<string, mixed>
     */
    public function employeeData(): array
    {
        return collect($this->validated())->except(['photo', 'documents'])->all();
    }

    private function employee(): ?Employee
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee ? $employee : null;
    }

    /**
     * Field names used in validation messages.
     *
     * @return array<string, string>
     */
    public static function attributeNames(): array
    {
        return [
            'branch_id' => __('branch'),
            'department_id' => __('department'),
            'designation_id' => __('designation'),
            'shift_id' => __('shift'),
            'reports_to_id' => __('manager'),
            'id_type' => __('identity document'),
            'id_number' => __('MyKad / passport number'),
            'tax_payer_id' => __('income tax number'),
            'photo' => __('profile image'),
        ];
    }

    /**
     * MyKad numbers are stored as YYMMDD-PB-####; passport numbers upper-cased without spaces.
     *
     * @param  array<string, mixed>  $input
     */
    public static function normalizeIdNumber(array $input): ?string
    {
        $number = trim((string) ($input['id_number'] ?? ''));

        if ($number === '') {
            return null;
        }

        return ($input['id_type'] ?? null) === 'mykad'
            ? Employee::formatMyKad($number)
            : strtoupper((string) preg_replace('/\s+/', '', $number));
    }

    /**
     * Employee field rules, shared by the wizard and the CSV import (which validates each row).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function rulesFor(array $input, ?Employee $employee = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee?->user_id)],
            'password' => [$employee ? 'nullable' : 'required', 'string', Password::min(8)],
            'employee_id' => ['nullable', 'string', 'max:50', Rule::unique('employees', 'employee_id')->ignore($employee?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(Employee::GENDERS)],
            'id_type' => ['nullable', 'required_with:id_number', Rule::in(Employee::ID_TYPES)],
            'id_number' => [
                'nullable', 'required_with:id_type', 'string', 'max:30',
                ($input['id_type'] ?? null) === 'mykad' ? 'regex:/^\d{6}-\d{2}-\d{4}$/' : 'regex:/^[A-Z0-9]{5,20}$/',
                Rule::unique('employees', 'id_number')->ignore($employee?->id),
            ],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            // The department must belong to the chosen branch, and the designation to the chosen department.
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('branch_id', (int) ($input['branch_id'] ?? 0))],
            'designation_id' => ['required', 'integer', Rule::exists('designations', 'id')->where('department_id', (int) ($input['department_id'] ?? 0))],
            'shift_id' => ['nullable', 'integer', Rule::exists('shifts', 'id')],
            'reports_to_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'date_of_joining' => ['required', 'date'],
            'employment_type' => ['required', Rule::in(Employee::EMPLOYMENT_TYPES)],
            'employee_status' => ['required', Rule::in(Employee::STATUSES)],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:255'],
            'emergency_contact_number' => ['nullable', 'string', 'max:30'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'bank_identifier_code' => ['nullable', 'string', 'max:50'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'tax_payer_id' => ['nullable', 'string', 'max:50'],
        ];
    }
}
