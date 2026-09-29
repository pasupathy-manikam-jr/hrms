<?php

namespace Tests\Feature\Hr;

use App\Models\Department;
use App\Models\Designation;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(Designation $designation, array $overrides = []): array
    {
        return [
            'name' => 'Nora Quinn',
            'email' => 'nora@example.com',
            'password' => 'Zx123456',
            'branch_id' => $designation->department->branch_id,
            'department_id' => $designation->department_id,
            'designation_id' => $designation->id,
            'date_of_joining' => '2026-01-15',
            'employment_type' => 'Full-time',
            'employee_status' => 'probation',
            'bank_name' => 'First Bank',
            ...$overrides,
        ];
    }

    public function test_list_can_be_searched_and_filtered()
    {
        Employee::factory()->count(3)->create();
        $zeta = Employee::factory()->for(User::factory()->state(['name' => 'Zeta Moon']))->create(['employee_status' => 'terminated']);

        $this->actingAs($this->userWithRole('hr'))
            ->get(route('hr.employees.index', ['search' => 'Zeta']))
            ->assertInertia(fn ($page) => $page
                ->component('hr/employees/index')
                ->has('employees.data', 1)
                ->where('employees.data.0.user.name', 'Zeta Moon')
                ->where('statusCounts', ['all' => 4, 'active' => 3, 'inactive' => 0, 'probation' => 0, 'terminated' => 1]));

        $this->get(route('hr.employees.index', ['department' => $zeta->department_id]))
            ->assertInertia(fn ($page) => $page->has('employees.data', 1)->where('filters.department', (string) $zeta->department_id));

        $this->get(route('hr.employees.index', ['status' => 'active', 'branch' => $zeta->branch_id]))
            ->assertInertia(fn ($page) => $page->has('employees.data', 0));

        $this->get(route('hr.employees.index', ['designation' => $zeta->designation_id, 'status' => 'terminated']))
            ->assertInertia(fn ($page) => $page->has('employees.data', 1));
    }

    public function test_employees_only_see_their_own_record()
    {
        Employee::factory()->count(2)->create();
        $user = $this->userWithRole('employee');
        $own = Employee::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('hr.employees.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('employees.data', 1)
                ->where('employees.data.0.id', $own->id)
                ->where('statusCounts.all', 1));
    }

    public function test_creating_an_employee_creates_a_user_with_the_employee_role()
    {
        $designation = Designation::factory()->create();
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.employees.store'), $this->payload($designation))->assertSessionHasNoErrors();

        $user = User::where('email', 'nora@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('employee'));
        $this->assertTrue(Hash::check('Zx123456', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('probation', $user->employee->employee_status);
        $this->assertMatchesRegularExpression('/^EMP\d{6}$/', $user->employee->employee_id);
        $this->assertSame('First Bank', $user->employee->bank_name);
    }

    public function test_validation_rejects_bad_input_and_mismatched_hierarchy()
    {
        $designation = Designation::factory()->create();
        $otherDepartment = Department::factory()->create();
        $otherDesignation = Designation::factory()->create();
        $taken = Employee::factory()->create();
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.employees.store'), ['employee_status' => 'bogus', 'employment_type' => 'bogus'])
            ->assertSessionHasErrors(['name', 'email', 'password', 'branch_id', 'department_id', 'designation_id', 'date_of_joining', 'employment_type', 'employee_status']);

        // The department must belong to the branch, and the designation to the department.
        $this->post(route('hr.employees.store'), $this->payload($designation, ['department_id' => $otherDepartment->id]))
            ->assertSessionHasErrors(['department_id', 'designation_id']);
        $this->post(route('hr.employees.store'), $this->payload($designation, ['designation_id' => $otherDesignation->id]))
            ->assertSessionHasErrors(['designation_id'])->assertSessionDoesntHaveErrors(['department_id']);

        $this->post(route('hr.employees.store'), $this->payload($designation, ['email' => $taken->user->email, 'employee_id' => $taken->employee_id]))
            ->assertSessionHasErrors(['email', 'employee_id']);

        $this->assertDatabaseMissing('users', ['email' => 'nora@example.com']);
    }

    public function test_employees_can_be_updated_and_deleted_with_their_user()
    {
        $employee = Employee::factory()->create();
        $user = $employee->user;
        $oldPassword = $user->password;
        $this->actingAs($this->userWithRole());

        $this->put(route('hr.employees.update', $employee), $this->payload($employee->designation, [
            'email' => $user->email,
            'password' => '',
            'name' => 'Renamed Person',
            'employee_id' => $employee->employee_id,
            'employee_status' => 'inactive',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Renamed Person', $user->fresh()->name);
        $this->assertSame($oldPassword, $user->fresh()->password);
        $this->assertSame('inactive', $employee->fresh()->employee_status);

        $this->delete(route('hr.employees.destroy', $employee));
        $this->assertModelMissing($employee);
        $this->assertModelMissing($user);
    }

    public function test_permissions_are_enforced()
    {
        $employee = Employee::factory()->create();
        $designation = Designation::factory()->create();

        // HR has no delete-employees permission in the demo.
        $this->actingAs($this->userWithRole('hr'))->delete(route('hr.employees.destroy', $employee))->assertForbidden();

        $this->actingAs($this->userWithRole('employee'));
        $this->post(route('hr.employees.store'), $this->payload($designation))->assertForbidden();
        $this->put(route('hr.employees.update', $employee), $this->payload($designation))->assertForbidden();
        $this->delete(route('hr.employees.destroy', $employee))->assertForbidden();
        $this->assertModelExists($employee);
    }

    public function test_wizard_pages_render_for_create_and_edit()
    {
        $this->withoutVite();
        DocumentType::create(['name' => 'Identity Proof', 'is_required' => true]);
        $employee = Employee::factory()->create();
        $this->actingAs($this->userWithRole());

        $this->get(route('hr.employees.create'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/employees/form')
                ->where('employee', null)
                ->has('documentTypes', 1)
                ->has('nextEmployeeId'));

        $this->get(route('hr.employees.edit', $employee))
            ->assertInertia(fn ($page) => $page->where('employee.id', $employee->id)->has('employee.documents', 0));

        $this->actingAs($this->userWithRole('employee'))->get(route('hr.employees.create'))->assertForbidden();
    }

    public function test_mykad_is_normalised_unique_and_passport_is_checked()
    {
        $designation = Designation::factory()->create();
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.employees.store'), $this->payload($designation, ['id_type' => 'mykad', 'id_number' => '9504121456']))
            ->assertSessionHasErrors('id_number');
        $this->post(route('hr.employees.store'), $this->payload($designation, ['id_type' => 'passport', 'id_number' => 'A1!']))
            ->assertSessionHasErrors('id_number');

        $this->post(route('hr.employees.store'), $this->payload($designation, ['id_type' => 'mykad', 'id_number' => '950412 14 5678']))
            ->assertRedirect(route('hr.employees.index'));
        $this->assertSame('950412-14-5678', Employee::where('id_number', '950412-14-5678')->value('id_number'));

        $this->post(route('hr.employees.store'), $this->payload($designation, ['email' => 'other@example.com', 'id_type' => 'mykad', 'id_number' => '950412-14-5678']))
            ->assertSessionHasErrors('id_number');
    }

    public function test_required_documents_and_photo_are_uploaded_with_the_employee()
    {
        Storage::fake('local');
        $identity = DocumentType::create(['name' => 'Identity Proof', 'is_required' => true]);
        $optional = DocumentType::create(['name' => 'Experience Letters', 'is_required' => false]);
        $designation = Designation::factory()->create();
        $this->actingAs($this->userWithRole());

        $this->post(route('hr.employees.store'), $this->payload($designation))
            ->assertSessionHasErrors("documents.{$identity->id}");

        $this->post(route('hr.employees.store'), $this->payload($designation, [
            'photo' => UploadedFile::fake()->image('face.png'),
            'documents' => [$identity->id => UploadedFile::fake()->create('mykad.pdf', 20, 'application/pdf')],
        ]))->assertRedirect(route('hr.employees.index'));

        $employee = Employee::whereHas('user', fn ($q) => $q->where('email', 'nora@example.com'))->firstOrFail();
        $this->assertNotNull($employee->user->avatar_path);
        $document = $employee->documents()->firstOrFail();
        $this->assertSame('mykad.pdf', $document->file_name);
        $this->get(route('hr.employees.document', [$employee, $document]))->assertDownload('mykad.pdf');

        // Editing doesn't ask for documents already on file; optional ones stay optional.
        $this->put(route('hr.employees.update', $employee), $this->payload($designation, ['password' => '']))
            ->assertSessionHasNoErrors();
        $this->assertSame(0, $employee->documents()->where('document_type_id', $optional->id)->count());
    }

    public function test_wizard_steps_are_validated_one_at_a_time_with_precognition()
    {
        DocumentType::create(['name' => 'Identity Proof', 'is_required' => true]);
        $this->actingAs($this->userWithRole());
        $precognition = ['Precognition' => 'true', 'Precognition-Validate-Only' => 'name,email,password,id_type,id_number'];

        // Only the Personal step's fields are checked: no errors for branch or documents yet.
        $this->postJson(route('hr.employees.store'), ['id_type' => 'mykad'], $precognition)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'id_number'])
            ->assertJsonMissingValidationErrors(['branch_id', 'documents.1']);

        $this->postJson(route('hr.employees.store'), [
            'name' => 'Wizard Test', 'email' => 'wizard@example.com', 'password' => 'Zx123456',
            'id_type' => 'mykad', 'id_number' => '900101145678',
        ], $precognition)->assertNoContent()->assertHeader('Precognition-Success', 'true');

        $this->assertDatabaseMissing('users', ['email' => 'wizard@example.com']);
    }
}
