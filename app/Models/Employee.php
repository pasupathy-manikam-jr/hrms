<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The HR profile of a `users` row (1:1 via user_id).
 *
 * @property int $id
 * @property int $user_id
 * @property string $employee_id
 * @property Carbon|null $date_of_birth
 * @property Carbon|null $date_of_joining
 * @property string $employee_status
 * @property string|null $id_type
 * @property string|null $citizenship
 * @property string|null $marital_status
 * @property bool $spouse_working
 * @property int $tax_children
 * @property bool $tax_resident
 * @property bool $lindung24_opt_out
 */
#[Fillable([
    'user_id', 'employee_id', 'phone', 'date_of_birth', 'gender',
    'branch_id', 'department_id', 'designation_id', 'shift_id', 'date_of_joining', 'employment_type', 'employee_status',
    'address_line_1', 'address_line_2', 'city', 'state', 'country', 'postal_code',
    'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_number',
    'bank_name', 'account_holder_name', 'account_number', 'bank_identifier_code', 'bank_branch', 'tax_payer_id', 'id_type', 'id_number',
    'citizenship', 'marital_status', 'spouse_working', 'tax_children', 'tax_resident', 'epf_number', 'lindung24_opt_out',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    public const STATUSES = ['active', 'inactive', 'probation', 'terminated'];

    public const GENDERS = ['male', 'female', 'other'];

    /** Identity documents: MyKad for Malaysians and PRs, passport for foreign staff. */
    public const ID_TYPES = ['mykad', 'passport'];

    /**
     * A MyKad number in its standard 12-digit form (YYMMDD-PB-####), whether typed with dashes, spaces or none.
     */
    public static function formatMyKad(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number) ?? '';

        return strlen($digits) === 12 ? substr($digits, 0, 6).'-'.substr($digits, 6, 2).'-'.substr($digits, 8) : $number;
    }

    public const EMPLOYMENT_TYPES = ['Full-time', 'Part-time', 'Contract', 'Temporary'];

    public const MARITAL_STATUSES = ['single', 'married', 'divorced', 'widowed'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'branch_id' => 'integer',
            'department_id' => 'integer',
            'designation_id' => 'integer',
            'shift_id' => 'integer',
            'date_of_birth' => 'date:Y-m-d',
            'date_of_joining' => 'date:Y-m-d',
            'spouse_working' => 'boolean',
            'tax_children' => 'integer',
            'tax_resident' => 'boolean',
            'lindung24_opt_out' => 'boolean',
        ];
    }

    /**
     * Everyone for manage-any-employees; only the user's own record for manage-own-employees.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-any-employees')) {
            $query->where('user_id', $user->can('manage-own-employees') ? $user->id : 0);
        }
    }

    /**
     * The next free code in the demo's EMP000001 format.
     */
    public static function nextCode(): string
    {
        $number = (int) static::query()->max('id');

        do {
            $code = 'EMP'.str_pad((string) ++$number, 6, '0', STR_PAD_LEFT);
        } while (static::query()->where('employee_id', $code)->exists());

        return $code;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Designation, $this>
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * Today's attendance record (company timezone), or null before clocking in.
     */
    public function todayAttendance(): ?AttendanceRecord
    {
        return $this->attendanceRecords()->whereDate('date', AttendanceRecord::now()->toDateString())->first();
    }

    /**
     * Uploaded identity, address, education and other documents, one per document type.
     *
     * @return HasMany<EmployeeDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }
}
