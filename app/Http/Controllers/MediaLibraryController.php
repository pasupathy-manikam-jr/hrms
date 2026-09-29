<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\MediaDirectory;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaLibraryController extends Controller
{
    /** Files per upload. */
    private const MAX_FILES = 10;

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $directories = MediaDirectory::query()->visibleTo($user)->orderBy('name')->get(['id', 'name', 'created_by']);
        $visible = Media::query()->visibleTo($user);
        $counts = TableQuery::countBy($visible, 'directory_id');

        $query = $visible->with('directory:id,name', 'creator:id,name')
            ->when($request->input('directory_id') === 'none', fn ($q) => $q->whereNull('directory_id'))
            ->when($request->integer('directory_id'), fn ($q, $id) => $q->where('directory_id', $id));

        return Inertia::render('media-library/index', [
            'media' => TableQuery::paginate($query, $request, ['name', 'file_name'], ['name', 'file_size', 'created_at']),
            'directories' => $directories->map(fn (MediaDirectory $d) => [...$d->only(['id', 'name', 'created_by']), 'media_count' => (int) ($counts[$d->id] ?? 0)]),
            'totalCount' => (int) $counts->sum(),
            'unfiledCount' => (int) ($counts[''] ?? 0),
            'uploadTypes' => Media::UPLOAD_EXTENSIONS,
            'uploadMaxKb' => Media::UPLOAD_MAX_KB,
            'filters' => TableQuery::filters($request, ['directory_id', 'view']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],
            'files.*' => Media::uploadRules(true),
            'directory_id' => ['nullable', 'integer', $this->visibleDirectory($request)],
        ]);

        /** @var list<UploadedFile> $files */
        $files = $request->file('files');
        DB::transaction(function () use ($files, $data) {
            foreach ($files as $file) {
                $media = new Media([
                    'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    'directory_id' => $data['directory_id'] ?? null,
                ]);
                $media->attachUpload($file)->save();
            }
        });

        return $this->done(trans_choice('{1} File uploaded successfully.|[2,*] :count files uploaded successfully.', count($files)));
    }

    /**
     * Rename and / or move to another folder.
     */
    public function update(Request $request, Media $media): RedirectResponse
    {
        $this->authorizeMedia($request, $media);

        $media->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'directory_id' => ['nullable', 'integer', $this->visibleDirectory($request)],
        ]));

        return $this->done(__('File updated successfully.'));
    }

    public function destroy(Request $request, Media $media): RedirectResponse
    {
        $this->authorizeMedia($request, $media);

        $media->delete();

        return $this->done(__('File deleted successfully.'));
    }

    public function download(Request $request, Media $media): StreamedResponse
    {
        $this->authorizeMedia($request, $media);

        return $media->downloadUpload();
    }

    public function preview(Request $request, Media $media): StreamedResponse
    {
        $this->authorizeMedia($request, $media);

        return $media->previewUpload();
    }

    public function storeDirectory(Request $request): RedirectResponse
    {
        MediaDirectory::create($request->validate(['name' => ['required', 'string', 'max:255']]));

        return $this->done(__('Folder created successfully.'));
    }

    public function updateDirectory(Request $request, MediaDirectory $mediaDirectory): RedirectResponse
    {
        $this->authorizeDirectory($request, $mediaDirectory);

        $mediaDirectory->update($request->validate(['name' => ['required', 'string', 'max:255']]));

        return $this->done(__('Folder renamed successfully.'));
    }

    /**
     * Files in the folder are kept and become unfiled.
     */
    public function destroyDirectory(Request $request, MediaDirectory $mediaDirectory): RedirectResponse
    {
        $this->authorizeDirectory($request, $mediaDirectory);

        $mediaDirectory->delete();

        return $this->done(__('Folder deleted successfully.'));
    }

    private function authorizeMedia(Request $request, Media $media): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($media->isVisibleTo($user), 404);
    }

    private function authorizeDirectory(Request $request, MediaDirectory $directory): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($directory->isVisibleTo($user), 404);
    }

    /**
     * Files may only be put in folders the user can see.
     */
    private function visibleDirectory(Request $request): In
    {
        /** @var User $user */
        $user = $request->user();

        return Rule::in(MediaDirectory::query()->visibleTo($user)->pluck('id'));
    }
}
