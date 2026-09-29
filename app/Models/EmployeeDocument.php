<?php

namespace App\Models;

use App\Models\Concerns\StoresUploads;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An employee's file for one document type (Identity Proof, Address Proof…).
 *
 * @property int $id
 * @property int $employee_id
 * @property int $document_type_id
 * @property string|null $file_path
 * @property string|null $file_name
 */
#[Fillable(['employee_id', 'document_type_id', 'file_path', 'file_name', 'file_type', 'file_size'])]
class EmployeeDocument extends Model
{
    use StoresUploads;

    public const UPLOAD_DIRECTORY = 'employee-documents';

    /** @var list<string> */
    protected $hidden = ['file_path'];

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
