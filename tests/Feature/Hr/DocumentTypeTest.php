<?php

namespace Tests\Feature\Hr;

use App\Models\DocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered()
    {
        DocumentType::factory()->count(3)->create();
        DocumentType::factory()->create(['name' => 'Passport Copy', 'is_required' => true]);

        $this->actingAs($this->userWithRole())
            ->get(route('hr.document-types.index', ['search' => 'Passport']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/document-types/index')
                ->has('documentTypes.data', 1)
                ->where('documentTypes.data.0.is_required', true));

        $this->get(route('hr.document-types.index', ['required' => 'yes']))->assertInertia(fn ($page) => $page->has('documentTypes.data', 1));
        $this->get(route('hr.document-types.index', ['required' => 'no']))->assertInertia(fn ($page) => $page->has('documentTypes.data', 3));
    }

    public function test_document_types_can_be_created_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('hr.document-types.store'), ['name' => '', 'is_required' => 'maybe'])->assertSessionHasErrors(['name', 'is_required']);
        $this->post(route('hr.document-types.store'), ['name' => 'Tax Form', 'is_required' => true])->assertSessionHasNoErrors();

        $documentType = DocumentType::where('name', 'Tax Form')->firstOrFail();
        $this->assertTrue($documentType->is_required);

        $this->put(route('hr.document-types.update', $documentType), ['name' => 'Tax Form W-4', 'is_required' => false])->assertSessionHasNoErrors();
        $this->assertFalse($documentType->fresh()->is_required);

        $this->delete(route('hr.document-types.destroy', $documentType));
        $this->assertModelMissing($documentType);
    }

    public function test_employees_cannot_manage_document_types()
    {
        $documentType = DocumentType::factory()->create();
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.document-types.index'))->assertForbidden();
        $this->post(route('hr.document-types.store'), ['name' => 'X'])->assertForbidden();
        $this->delete(route('hr.document-types.destroy', $documentType))->assertForbidden();
        $this->assertModelExists($documentType);
    }
}
