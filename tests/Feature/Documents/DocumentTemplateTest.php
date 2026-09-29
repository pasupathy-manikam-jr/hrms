<?php

namespace Tests\Feature\Documents;

use App\Models\Designation;
use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_templates_can_be_created_filtered_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole('hr'));
        $category = DocumentCategory::create(['name' => 'Employment', 'color' => '#F59E0B']);

        $this->post(route('hr.documents.document-templates.store'), ['name' => '', 'status' => 'x'])->assertSessionHasErrors(['name', 'template_content', 'status']);
        $this->post(route('hr.documents.document-templates.store'), [
            'name' => 'Experience Letter', 'category_id' => $category->id, 'template_content' => 'Dear {employee_name}', 'is_default' => true, 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->get(route('hr.documents.document-templates.index', ['category_id' => $category->id]))
            ->assertInertia(fn ($page) => $page
                ->component('hr/documents/document-templates/index')
                ->has('documentTemplates.data', 1)
                ->where('documentTemplates.data.0.category.name', 'Employment')
                ->where('statusCounts.active', 1));

        $template = DocumentTemplate::where('name', 'Experience Letter')->firstOrFail();
        $this->put(route('hr.documents.document-templates.update', $template), ['name' => 'NOC', 'template_content' => 'x', 'status' => 'inactive'])->assertSessionHasNoErrors();
        $this->assertSame('inactive', $template->fresh()->status);

        $this->delete(route('hr.documents.document-templates.destroy', $template));
        $this->assertModelMissing($template);
    }

    public function test_preview_fills_placeholders_and_escapes_employee_data()
    {
        $this->actingAs($this->userWithRole());
        $user = User::factory()->create(['name' => '<script>alert(1)</script> Jane']);
        $employee = Employee::factory()->create(['user_id' => $user->id, 'designation_id' => Designation::factory()->create(['name' => 'R&D Lead'])->id]);
        $template = DocumentTemplate::create([
            'name' => 'Letter',
            'template_content' => "<p>Dear {employee_name},</p>\nYou are our {{ designation }} at {company_name}. {unknown_field}",
        ]);

        $content = $this->getJson(route('hr.documents.document-templates.preview', [$template, 'employee_id' => $employee->id]))
            ->assertOk()
            ->json('content');

        $this->assertStringContainsString('<p>Dear &lt;script&gt;alert(1)&lt;/script&gt; Jane,</p>', $content);
        $this->assertStringContainsString('our R&amp;D Lead at HRM.', $content);
        $this->assertStringContainsString('{unknown_field}', $content);

        $this->getJson(route('hr.documents.document-templates.preview', [$template, 'employee_id' => 999]))->assertNotFound();
    }

    public function test_employees_cannot_manage_or_preview_templates()
    {
        $template = DocumentTemplate::create(['name' => 'Letter', 'template_content' => 'x']);
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.documents.document-templates.index'))->assertForbidden();
        $this->getJson(route('hr.documents.document-templates.preview', [$template, 'employee_id' => 1]))->assertForbidden();
        $this->delete(route('hr.documents.document-templates.destroy', $template))->assertForbidden();
    }

    public function test_template_page_shows_content_placeholders_and_employees()
    {
        $this->withoutVite();
        $template = DocumentTemplate::create(['name' => 'Letter', 'template_content' => 'Dear {{employee_name}} of {company_name}', 'status' => 'active']);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.documents.document-templates.show', $template))
            ->assertInertia(fn ($page) => $page
                ->component('hr/documents/document-templates/show')
                ->where('template.id', $template->id)
                ->where('placeholders', ['employee_name', 'company_name'])
                ->has('employees'));
    }

    public function test_own_scope_users_cannot_open_other_peoples_templates()
    {
        $this->withoutVite();
        $this->seed(RolesSeeder::class);
        $user = User::factory()->create()->givePermissionTo(['manage-document-templates', 'manage-own-document-templates']);
        $other = DocumentTemplate::create(['name' => 'Other', 'template_content' => 'x', 'status' => 'active']);

        $this->actingAs($user)->get(route('hr.documents.document-templates.show', $other))->assertNotFound();

        $own = DocumentTemplate::create(['name' => 'Mine', 'template_content' => 'x', 'status' => 'active']);
        $this->get(route('hr.documents.document-templates.show', $own))->assertOk();
    }

    public function test_templates_can_be_locked_downloaded_as_pdf_and_filtered_by_default()
    {
        $default = DocumentTemplate::create(['name' => 'Offer Letter', 'template_content' => 'Dear {{employee_name}}', 'status' => 'active', 'is_default' => true]);
        DocumentTemplate::create(['name' => 'Custom Letter', 'template_content' => 'Hello', 'status' => 'active', 'is_default' => false]);
        $this->actingAs($this->userWithRole());

        $this->get(route('hr.documents.document-templates.index', ['type' => 'default']))
            ->assertInertia(fn ($page) => $page->has('documentTemplates.data', 1)->where('documentTemplates.data.0.name', 'Offer Letter'));
        $this->get(route('hr.documents.document-templates.index', ['type' => 'custom']))
            ->assertInertia(fn ($page) => $page->has('documentTemplates.data', 1)->where('documentTemplates.data.0.name', 'Custom Letter'));

        $this->put(route('hr.documents.document-templates.toggle-status', $default))->assertSessionHasNoErrors();
        $this->assertSame('inactive', $default->fresh()->status);

        $response = $this->get(route('hr.documents.document-templates.download', $default))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('offer-letter.pdf', $response->headers->get('Content-Disposition'));
    }
}
