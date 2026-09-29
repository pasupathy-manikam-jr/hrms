<?php

namespace Tests\Feature\Documents;

use App\Models\DocumentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_and_filtered()
    {
        $this->actingAs($this->userWithRole());
        DocumentCategory::create(['name' => 'Legal Documents', 'color' => '#6B7280', 'is_mandatory' => false]);
        DocumentCategory::create(['name' => 'Compliance Documents', 'color' => '#DC2626', 'is_mandatory' => true, 'status' => 'inactive']);

        $this->get(route('hr.documents.document-categories.index', ['search' => 'Legal']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/documents/document-categories/index')
                ->has('documentCategories.data', 1)
                ->where('documentCategories.data.0.documents_count', 0));

        $this->get(route('hr.documents.document-categories.index', ['is_mandatory' => 'yes']))->assertInertia(fn ($page) => $page->has('documentCategories.data', 1));
        $this->get(route('hr.documents.document-categories.index', ['status' => 'active']))->assertInertia(fn ($page) => $page
            ->has('documentCategories.data', 1)
            ->where('documentCategories.data.0.name', 'Legal Documents'));
    }

    public function test_categories_can_be_created_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('hr.documents.document-categories.store'), ['name' => '', 'color' => 'red', 'status' => 'x'])
            ->assertSessionHasErrors(['name', 'color', 'status']);
        $this->post(route('hr.documents.document-categories.store'), ['name' => 'Medical', 'color' => '#8B5CF6', 'is_mandatory' => true, 'status' => 'active'])
            ->assertSessionHasNoErrors();

        $category = DocumentCategory::where('name', 'Medical')->firstOrFail();
        $this->assertTrue($category->is_mandatory);

        $this->put(route('hr.documents.document-categories.update', $category), ['name' => 'Medical Records', 'color' => '#8B5CF6', 'status' => 'inactive'])
            ->assertSessionHasNoErrors();
        $this->assertSame('inactive', $category->fresh()->status);

        $this->delete(route('hr.documents.document-categories.destroy', $category));
        $this->assertModelMissing($category);
    }

    public function test_employees_cannot_manage_categories()
    {
        $category = DocumentCategory::create(['name' => 'Legal', 'color' => '#6B7280']);
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.documents.document-categories.index'))->assertForbidden();
        $this->post(route('hr.documents.document-categories.store'), ['name' => 'X'])->assertForbidden();
        $this->delete(route('hr.documents.document-categories.destroy', $category))->assertForbidden();
        $this->assertModelExists($category);
    }

    public function test_it_can_be_locked_and_unlocked()
    {
        $record = DocumentCategory::create(['name' => 'Legal', 'color' => '#6B7280', 'status' => 'active']);
        $this->actingAs($this->userWithRole());

        $this->put(route('hr.documents.document-categories.toggle-status', $record))->assertSessionHasNoErrors();
        $this->assertSame('inactive', $record->fresh()->status);

        $this->actingAs($this->userWithRole('employee'))
            ->put(route('hr.documents.document-categories.toggle-status', $record))
            ->assertForbidden();
    }

    public function test_category_icon_must_be_one_of_the_offered_icons()
    {
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.documents.document-categories.store'), ['name' => 'Payroll', 'color' => '#10B981', 'icon' => 'Rocket', 'status' => 'active'])->assertSessionHasErrors('icon');
        $this->post(route('hr.documents.document-categories.store'), ['name' => 'Payroll', 'color' => '#10B981', 'icon' => 'Banknote', 'status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('Banknote', DocumentCategory::where('name', 'Payroll')->value('icon'));
    }
}
