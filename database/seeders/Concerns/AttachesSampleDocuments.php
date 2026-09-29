<?php

namespace Database\Seeders\Concerns;

use App\Support\SimplePdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Gives seeded records (models using StoresUploads) a generated one-page PDF, so their document
 * links work in the demo. Never uses WorkDo's files.
 */
trait AttachesSampleDocuments
{
    /**
     * @param  string  $directory  the model's UPLOAD_DIRECTORY
     * @param  list<string>  $lines  first line is the title
     */
    protected function attachSampleDocument(Model $record, string $directory, string $fileName, array $lines): void
    {
        $contents = SimplePdf::make([...$lines, '', 'Sample document generated for the demo.']);
        $path = $directory.'/seed-'.$record->getKey().'-'.$fileName;
        Storage::disk('local')->put($path, $contents);

        $record->forceFill([
            'file_path' => $path,
            'file_name' => $fileName,
            'file_type' => 'application/pdf',
            'file_size' => strlen($contents),
        ])->save();
    }
}
