<?php

namespace Tests\Feature\Documents;

use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_templates_can_be_created_filtered_updated_and_deleted()
    {
        $this->actingAs($this->userWithRole('hr'));
        $type = ContractType::create(['name' => 'Permanent']);

        $this->post(route('hr.contracts.contract-templates.store'), ['name' => 'X', 'contract_type_id' => 999, 'status' => 'active'])
            ->assertSessionHasErrors(['contract_type_id', 'template_content']);
        $this->post(route('hr.contracts.contract-templates.store'), [
            'name' => 'Standard Contract', 'contract_type_id' => $type->id, 'template_content' => 'Between {{company_name}} and {{employee_name}}', 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->get(route('hr.contracts.contract-templates.index', ['contract_type_id' => $type->id]))
            ->assertInertia(fn ($page) => $page
                ->component('hr/contracts/contract-templates/index')
                ->has('contractTemplates.data', 1)
                ->where('contractTemplates.data.0.contract_type.name', 'Permanent'));

        $template = ContractTemplate::where('name', 'Standard Contract')->firstOrFail();
        $employee = Employee::factory()->create();

        $this->getJson(route('hr.contracts.contract-templates.preview', [$template, 'employee_id' => $employee->id]))
            ->assertOk()
            ->assertJsonPath('content', 'Between HRM and '.e($employee->user->name));

        $this->put(route('hr.contracts.contract-templates.update', $template), ['name' => 'Renamed', 'contract_type_id' => '', 'template_content' => 'x', 'status' => 'inactive'])->assertSessionHasNoErrors();
        $this->assertNull($template->fresh()->contract_type_id);

        $this->delete(route('hr.contracts.contract-templates.destroy', $template));
        $this->assertModelMissing($template);
    }

    public function test_employees_cannot_manage_contract_templates()
    {
        $this->actingAs($this->userWithRole('employee'));

        $this->get(route('hr.contracts.contract-templates.index'))->assertForbidden();
        $this->post(route('hr.contracts.contract-templates.store'), ['name' => 'X'])->assertForbidden();
    }

    public function test_template_page_shows_content_placeholders_and_employees()
    {
        $this->withoutVite();
        $template = ContractTemplate::create(['name' => 'Letter', 'template_content' => 'Dear {{employee_name}} of {company_name}', 'status' => 'active']);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.contracts.contract-templates.show', $template))
            ->assertInertia(fn ($page) => $page
                ->component('hr/contracts/contract-templates/show')
                ->where('template.id', $template->id)
                ->where('placeholders', ['employee_name', 'company_name'])
                ->has('employees'));
    }

    public function test_own_scope_users_cannot_open_other_peoples_templates()
    {
        $this->withoutVite();
        $this->seed(RolesSeeder::class);
        $user = User::factory()->create()->givePermissionTo(['manage-contract-templates', 'manage-own-contract-templates']);
        $other = ContractTemplate::create(['name' => 'Other', 'template_content' => 'x', 'status' => 'active']);

        $this->actingAs($user)->get(route('hr.contracts.contract-templates.show', $other))->assertNotFound();

        $own = ContractTemplate::create(['name' => 'Mine', 'template_content' => 'x', 'status' => 'active']);
        $this->get(route('hr.contracts.contract-templates.show', $own))->assertOk();
    }

    public function test_templates_can_be_locked_downloaded_as_pdf_and_filtered_by_default()
    {
        $default = ContractTemplate::create(['name' => 'Offer Letter', 'template_content' => 'Dear {{employee_name}}', 'status' => 'active', 'is_default' => true]);
        ContractTemplate::create(['name' => 'Custom Letter', 'template_content' => 'Hello', 'status' => 'active', 'is_default' => false]);
        $this->actingAs($this->userWithRole());

        $this->get(route('hr.contracts.contract-templates.index', ['type' => 'default']))
            ->assertInertia(fn ($page) => $page->has('contractTemplates.data', 1)->where('contractTemplates.data.0.name', 'Offer Letter'));
        $this->get(route('hr.contracts.contract-templates.index', ['type' => 'custom']))
            ->assertInertia(fn ($page) => $page->has('contractTemplates.data', 1)->where('contractTemplates.data.0.name', 'Custom Letter'));

        $this->put(route('hr.contracts.contract-templates.toggle-status', $default))->assertSessionHasNoErrors();
        $this->assertSame('inactive', $default->fresh()->status);

        $response = $this->get(route('hr.contracts.contract-templates.download', $default))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('offer-letter.pdf', $response->headers->get('Content-Disposition'));
    }
}
