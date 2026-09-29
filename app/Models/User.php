<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $lang
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string $status
 * @property int|null $reports_to_id
 * @property string|null $avatar_path
 * @property-read string|null $avatar
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'lang', 'status', 'reports_to_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'avatar_path'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    public const STATUSES = ['active', 'inactive'];

    /** @var list<string> */
    protected $appends = ['avatar'];

    /**
     * URL of the uploaded photo (null means the UI draws a generated avatar).
     *
     * @return Attribute<string|null, never>
     */
    protected function avatar(): Attribute
    {
        return Attribute::get(fn () => $this->avatar_path
            ? route('users.avatar', ['user' => $this->id, 'v' => substr(md5($this->avatar_path), 0, 8)])
            : null);
    }

    /**
     * Store a new photo on the private disk, replacing (and deleting) the previous one.
     */
    public function replaceAvatar(?UploadedFile $photo): void
    {
        if (! $photo) {
            return;
        }

        $old = $this->avatar_path;
        $this->forceFill(['avatar_path' => $photo->store('avatars', 'local')])->save();

        if ($old) {
            Storage::disk('local')->delete($old);
        }
    }

    public function isActive(): bool
    {
        return $this->status !== 'inactive';
    }

    /**
     * The user's HR profile, when the user is an employee.
     *
     * @return HasOne<Employee, $this>
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Who this user reports to (organization chart).
     *
     * @return BelongsTo<User, $this>
     */
    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reports_to_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(User::class, 'reports_to_id');
    }

    /**
     * Whether $candidate is this user or anywhere below them, i.e. making this user
     * report to $candidate would create a cycle.
     */
    public function isOrManages(User $candidate): bool
    {
        for ($user = $candidate, $steps = 0; $user && $steps < 100; $user = $user->reportsTo, $steps++) {
            if ($user->is($this)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Names of every permission the user holds, directly or via roles.
     *
     * One plucked query; getAllPermissions() hydrates each model and costs ~75ms for the company role.
     *
     * @return Collection<int, string>
     */
    public function permissionNames(): Collection
    {
        return Permission::query()
            ->where(fn ($query) => $query
                ->whereHas('roles', fn ($roles) => $roles->whereIn('id', $this->roles()->select('id')))
                ->orWhereHas('users', fn ($users) => $users->whereKey($this->getKey())))
            ->orderBy('name')
            ->pluck('name');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
