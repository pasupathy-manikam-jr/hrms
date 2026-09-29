<?php

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Models\MediaDirectory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function upload(User $user, string $name = 'logo.png', ?MediaDirectory $directory = null): Media
    {
        $media = new Media(['name' => pathinfo($name, PATHINFO_FILENAME), 'directory_id' => $directory?->id, 'created_by' => $user->id]);
        $file = str_ends_with($name, '.png') ? UploadedFile::fake()->image($name, 10, 10) : UploadedFile::fake()->create($name, 5, 'application/pdf');
        $media->attachUpload($file)->save();

        return $media;
    }

    public function test_upload_list_search_and_filter_by_folder(): void
    {
        $user = $this->userWithRole('company');
        $branding = MediaDirectory::create(['name' => 'Branding']);

        $this->actingAs($user)->post(route('media-library.store'), [
            'files' => [UploadedFile::fake()->image('company-logo.png', 20, 20), UploadedFile::fake()->create('handbook.pdf', 20, 'application/pdf')],
            'directory_id' => $branding->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Media::query()->where('directory_id', $branding->id)->count());
        $logo = Media::query()->where('name', 'company-logo')->sole();
        Storage::disk('local')->assertExists((string) $logo->file_path);
        $this->assertSame($user->id, $logo->created_by);
        $this->upload($user, 'unfiled.pdf');

        $this->get(route('media-library'))
            ->assertInertia(fn ($page) => $page
                ->component('media-library/index')
                ->has('media.data', 3)
                ->missing('media.data.0.file_path')
                ->where('totalCount', 3)
                ->where('unfiledCount', 1)
                ->where('directories.0.name', 'Branding')
                ->where('directories.0.media_count', 2));

        $this->get(route('media-library', ['search' => 'logo']))
            ->assertInertia(fn ($page) => $page->has('media.data', 1)->where('media.data.0.is_image', true));
        $this->get(route('media-library', ['directory_id' => $branding->id]))
            ->assertInertia(fn ($page) => $page->has('media.data', 2));
        $this->get(route('media-library', ['directory_id' => 'none']))
            ->assertInertia(fn ($page) => $page->has('media.data', 1)->where('media.data.0.name', 'unfiled')->where('media.data.0.is_image', false));
    }

    public function test_uploads_are_validated(): void
    {
        $this->actingAs($this->userWithRole('company'));

        $this->post(route('media-library.store'), [])->assertSessionHasErrors('files');
        $this->post(route('media-library.store'), ['files' => [UploadedFile::fake()->create('script.php', 1, 'text/x-php')]])->assertSessionHasErrors('files.0');
        $this->post(route('media-library.store'), ['files' => [UploadedFile::fake()->create('big.pdf', Media::UPLOAD_MAX_KB + 1, 'application/pdf')]])->assertSessionHasErrors('files.0');
        $this->post(route('media-library.store'), ['files' => [UploadedFile::fake()->image('a.png')], 'directory_id' => 999])->assertSessionHasErrors('directory_id');
        $this->assertSame(0, Media::query()->count());
    }

    public function test_preview_download_rename_move_and_delete(): void
    {
        $user = $this->userWithRole('company');
        $logo = $this->upload($user, 'logo.png');
        $pdf = $this->upload($user, 'policy.pdf');
        $folder = MediaDirectory::create(['name' => 'Docs']);
        $this->actingAs($user);

        $this->get(route('media-library.preview', $logo))->assertOk()->assertHeader('content-type', 'image/png');
        $this->get(route('media-library.preview', $pdf))->assertNotFound(); // images only
        $this->get(route('media-library.download', $pdf))->assertOk()->assertDownload('policy.pdf');

        $this->put(route('media-library.update', $pdf), ['name' => 'Leave Policy', 'directory_id' => $folder->id])->assertSessionHasNoErrors();
        $this->assertSame('Leave Policy', $pdf->fresh()?->name);
        $this->assertSame($folder->id, $pdf->fresh()->directory_id);
        $this->put(route('media-library.update', $pdf), ['name' => ''])->assertSessionHasErrors('name');

        // Deleting the folder keeps its files, unfiled.
        $this->delete(route('media-library.directories.destroy', $folder))->assertRedirect();
        $this->assertNull($pdf->fresh()->directory_id);

        $path = (string) $logo->file_path;
        $this->delete(route('media-library.destroy', $logo))->assertRedirect();
        $this->assertModelMissing($logo);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_folders_can_be_created_and_renamed(): void
    {
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('media-library.directories.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route('media-library.directories.store'), ['name' => 'Branding'])->assertSessionHasNoErrors();
        $folder = MediaDirectory::query()->sole();

        $this->put(route('media-library.directories.update', $folder), ['name' => 'Brand Assets'])->assertSessionHasNoErrors();
        $this->assertSame('Brand Assets', $folder->fresh()?->name);
    }

    public function test_with_manage_own_media_an_employee_cannot_see_download_or_preview_others_files(): void
    {
        $owner = $this->userWithRole('company');
        $theirs = $this->upload($owner, 'salary-sheet.png');
        $employee = $this->userWithRole('employee');
        $mine = $this->upload($employee, 'my-photo.png');

        $this->actingAs($employee)->get(route('media-library'))
            ->assertInertia(fn ($page) => $page->has('media.data', 1)->where('media.data.0.id', $mine->id)->where('totalCount', 1));

        $this->get(route('media-library.preview', $theirs))->assertNotFound();
        $this->get(route('media-library.download', $theirs))->assertNotFound();
        $this->put(route('media-library.update', $theirs), ['name' => 'Mine now'])->assertNotFound();
        $this->delete(route('media-library.destroy', $theirs))->assertNotFound();
        $this->assertModelExists($theirs);

        $this->get(route('media-library.preview', $mine))->assertOk();
        $this->get(route('media-library.download', $mine))->assertOk();
    }

    public function test_users_without_media_permissions_are_forbidden(): void
    {
        $media = $this->upload(User::factory()->create());
        $this->actingAs(User::factory()->create());

        $this->get(route('media-library'))->assertForbidden();
        $this->get(route('media-library.preview', $media))->assertForbidden();
        $this->get(route('media-library.download', $media))->assertForbidden();
    }
}
