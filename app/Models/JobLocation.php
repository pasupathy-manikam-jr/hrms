<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Database\Factories\JobLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_remote
 * @property string $status
 * @property int|null $created_by
 */
#[Fillable(['name', 'address', 'city', 'state', 'country', 'postal_code', 'is_remote', 'status', 'created_by'])]
class JobLocation extends Model
{
    /** @use HasFactory<JobLocationFactory> */
    use HasCreator, HasFactory;

    public const MODULE = 'job-locations';

    public const STATUSES = ['active', 'inactive'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_remote' => 'boolean', 'created_by' => 'integer'];
    }
}
