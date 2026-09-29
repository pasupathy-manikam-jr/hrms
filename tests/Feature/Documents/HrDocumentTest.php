<?php

namespace Tests\Feature\Documents;

use App\Models\DocumentAcknowledgment;
use App\Models\DocumentCategory;
use App\Models\HrDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function document(array $attributes = []): HrDocument
    {
        $category = DocumentCategory::firstOrCreate(['name' => 'Legal'], ['color' => '#6B7280']);
        $document = new HrDocument([
            'title' => 'Code of Conduct', 'category_id' => $category->id, 'version' => '1.0', 'status' => 'published', ...$attributes,
        ]);

        return tap($document->attachUpload(UploadedFile::fake()->createWithContent('policy.txt', 'Be nice.')))->save();
    }

    public function test_list_can_be_filtered_and_hides_the_storage_path()
    {
        Storage::fake('local');
        $this->document();
        $this->document(['title' => 'Draft Handbook', 'status' => 'draft', 'requires_acknowledgment' => true]);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.documents.hr-documents.index', ['status' => 'draft']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/documents/hr-documents/index')
                ->has('hrDocuments.data', 1)
                ->where('hrDocuments.data.0.title', 'Draft Handbook')
                ->missing('hrDocuments.data.0.file_path')
                ->where('statusCounts.all', 2)
                ->where('stats.needs_acknowledgment', 1));
    }

    public function test_documents_can_be_uploaded_replaced_and_deleted()
    {
        Storage::fake('local');
        $this->actingAs($this->userWithRole('hr'));
        $category = DocumentCategory::create(['name' => 'Legal', 'color' => '#6B7280']);
        $valid = ['title' => 'Leave Policy', 'category_id' => $category->id, 'version' => '1.0', 'status' => 'published'];

        $this->post(route('hr.documents.hr-documents.store'), $valid)->assertSessionHasErrors('file');
        $this->post(route('hr.documents.hr-documents.store'), [...$valid, 'file' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php')])->assertSessionHasErrors('file');
        $this->post(route('hr.documents.hr-documents.store'), [...$valid, 'file' => UploadedFile::fake()->create('big.pdf', 3000, 'application/pdf')])->assertSessionHasErrors('file');

        $this->post(route('hr.documents.hr-documents.store'), [...$valid, 'requires_acknowledgment' => '1', 'file' => UploadedFile::fake()->create('leave.pdf', 100, 'application/pdf')])
            ->assertSessionHasNoErrors();

        $document = HrDocument::where('title', 'Leave Policy')->firstOrFail();
        $this->assertTrue($document->requires_acknowledgment);
        $this->assertSame('leave.pdf', $document->file_name);
        $this->assertStringStartsWith('hr-documents/', $document->file_path);
        Storage::disk('local')->assertExists($document->file_path);
        $oldPath = $document->file_path;

        // Metadata-only update keeps the file; a new upload replaces it.
        $this->put(route('hr.documents.hr-documents.update', $document), [...$valid, 'version' => '1.1'])->assertSessionHasNoErrors();
        $this->assertSame($oldPath, $document->fresh()->file_path);

        $this->post(route('hr.documents.hr-documents.update', $document), [...$valid, '_method' => 'PUT', 'file' => UploadedFile::fake()->create('leave-v2.docx', 50)])
            ->assertSessionHasNoErrors();
        $document->refresh();
        $this->assertSame('leave-v2.docx', $document->file_name);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($document->file_path);

        $this->delete(route('hr.documents.hr-documents.destroy', $document));
        $this->assertModelMissing($document);
        Storage::disk('local')->assertMissing($document->file_path);
    }

    public function test_download_streams_the_private_file_and_counts_it()
    {
        Storage::fake('local');
        $document = $this->document();

        $this->actingAs($this->userWithRole('employee'))
            ->get(route('hr.documents.hr-documents.download', $document))
            ->assertOk()
            ->assertDownload('policy.txt');

        $this->assertSame(1, $document->fresh()->download_count);
    }

    public function test_employees_only_see_and_download_published_or_assigned_documents()
    {
        Storage::fake('local');
        $published = $this->document();
        $draft = $this->document(['title' => 'Draft Policy', 'status' => 'draft']);
        $assigned = $this->document(['title' => 'Assigned Draft', 'status' => 'under_review']);
        $employee = $this->userWithRole('employee');
        DocumentAcknowledgment::create(['document_id' => $assigned->id, 'user_id' => $employee->id]);

        $this->actingAs($employee)
            ->get(route('hr.documents.hr-documents.index'))
            ->assertInertia(fn ($page) => $page->has('hrDocuments.data', 2));

        $this->get(route('hr.documents.hr-documents.download', $draft))->assertNotFound();
        $this->get(route('hr.documents.hr-documents.download', $assigned))->assertOk();
        $this->get(route('hr.documents.hr-documents.download', $published))->assertOk();
        $this->assertSame(0, $draft->fresh()->download_count);

        $this->post(route('hr.documents.hr-documents.store'), ['title' => 'X'])->assertForbidden();
        $this->put(route('hr.documents.hr-documents.update', $published), ['title' => 'X'])->assertForbidden();
        $this->delete(route('hr.documents.hr-documents.destroy', $published))->assertForbidden();
    }

    public function test_guests_and_users_without_permission_cannot_download()
    {
        Storage::fake('local');
        $document = $this->document();

        $this->get(route('hr.documents.hr-documents.download', $document))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('hr.documents.hr-documents.download', $document))->assertForbidden();
    }

    public function test_document_status_can_be_updated()
    {
        $document = $this->document(['status' => 'draft']);
        $this->actingAs($this->userWithRole());

        $this->put(route('hr.documents.hr-documents.change-status', $document), ['status' => 'lost'])->assertSessionHasErrors('status');
        $this->put(route('hr.documents.hr-documents.change-status', $document), ['status' => 'published'])->assertSessionHasNoErrors();
        $this->assertSame('published', $document->fresh()->status);

        $this->actingAs($this->userWithRole('employee'))
            ->put(route('hr.documents.hr-documents.change-status', $document), ['status' => 'archived'])
            ->assertForbidden();
    }
}
