<?php

namespace App\Models\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One private attachment per record, kept on the `local` disk (never publicly served) in the
 * file_path / file_name / file_type / file_size columns. The using model defines UPLOAD_DIRECTORY.
 * Serve it through an authorized route with downloadUpload().
 */
trait StoresUploads
{
    /** The demo's allowed media types. */
    public const UPLOAD_EXTENSIONS = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt', 'csv'];

    public const UPLOAD_MAX_KB = 2048;

    /** Types that may be shown inline (images); everything else is download-only. */
    public const PREVIEW_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    protected static function bootStoresUploads(): void
    {
        static::deleted(fn (self $model) => $model->deleteUpload());
    }

    /**
     * Validation rules for the upload field.
     *
     * @return list<string>
     */
    public static function uploadRules(bool $required = false): array
    {
        $types = implode(',', self::UPLOAD_EXTENSIONS);

        return [$required ? 'required' : 'nullable', 'file', "mimes:{$types}", "extensions:{$types}", 'max:'.self::UPLOAD_MAX_KB];
    }

    /**
     * Store the file (replacing any previous one) and fill the file columns; the caller saves.
     */
    public function attachUpload(UploadedFile $file): static
    {
        $path = $file->store(static::UPLOAD_DIRECTORY, 'local');
        $this->deleteUpload();

        return $this->fill([
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ]);
    }

    /**
     * Attach the request's uploaded file (if one was sent) under the given field; the caller saves.
     */
    public function attachUploadFrom(Request $request, string $field = 'document'): static
    {
        return $request->hasFile($field) ? $this->attachUpload($request->file($field)) : $this;
    }

    public function hasUpload(): bool
    {
        return $this->file_path !== null && Storage::disk('local')->exists($this->file_path);
    }

    public function downloadUpload(): StreamedResponse
    {
        abort_unless($this->hasUpload(), 404);

        return Storage::disk('local')->download($this->file_path, $this->file_name);
    }

    /**
     * Whether the stored file is an image that can be previewed inline. The stored extension comes
     * from the file's contents (UploadedFile::store), not the client's name.
     */
    public function hasPreview(): bool
    {
        return $this->file_path !== null
            && in_array(strtolower(pathinfo($this->file_path, PATHINFO_EXTENSION)), self::PREVIEW_EXTENSIONS, true);
    }

    /**
     * Serve an image inline, e.g. as an <img> source, through an authorized route.
     */
    public function previewUpload(): StreamedResponse
    {
        abort_unless($this->hasPreview() && $this->hasUpload(), 404);

        return Storage::disk('local')->response($this->file_path, $this->file_name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function deleteUpload(): void
    {
        if ($this->file_path) {
            Storage::disk('local')->delete($this->file_path);
        }
    }
}
