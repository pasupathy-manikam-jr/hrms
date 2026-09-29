<?php

namespace App\Models;

use App\Models\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $template_content
 * @property bool $is_default
 * @property string $status
 * @property int|null $created_by
 */
#[Fillable(['name', 'description', 'contract_type_id', 'template_content', 'is_default', 'status', 'created_by'])]
class ContractTemplate extends Model
{
    use HasCreator;

    public const MODULE = 'contract-templates';

    public const STATUSES = ['active', 'inactive'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['contract_type_id' => 'integer', 'is_default' => 'boolean', 'created_by' => 'integer'];
    }

    /**
     * @return BelongsTo<ContractType, $this>
     */
    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class);
    }
}
