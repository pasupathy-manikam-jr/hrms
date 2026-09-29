<?php

namespace Tests\Feature\Documents;

use App\Models\DocumentAcknowledgment;
use App\Models\Employee;
use App\Models\HrDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentAcknowledgmentTest extends TestCase
{
    use RefreshDatabase;

    private function employeeUser(): User
    {
        $user = $this->userWithRole('employee');
        Employee::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function document(bool $requiresAcknowledgment = true): HrDocument
    {
        return HrDocument::create(['title' => 'Code of Conduct', 'version' => '1.0', 'status' => 'published', 'requires_acknowledgment' => $requiresAcknowledgment]);
    }

    public function test_hr_can_request_acknowledgments_and_see_who_has_not_acknowledged()
    {
        $alice = $this->employeeUser();
        $bob = $this->employeeUser();
        $document = $this->document();
        $this->actingAs($this->userWithRole('hr'));

        $this->post(route('hr.documents.document-acknowledgments.store'), ['document_id' => $this->document(false)->id])->assertSessionHasErrors('document_id');

        // Empty user_id = every employee; repeating it doesn't duplicate.
        $this->post(route('hr.documents.document-acknowledgments.store'), ['document_id' => $document->id, 'due_date' => today()->subDay()->toDateString()])->assertSessionHasNoErrors();
        $this->post(route('hr.documents.document-acknowledgments.store'), ['document_id' => $document->id, 'user_id' => $alice->id])->assertSessionHasNoErrors();
        $this->assertSame(2, DocumentAcknowledgment::count());

        DocumentAcknowledgment::where('user_id', $alice->id)->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);

        $this->get(route('hr.documents.document-acknowledgments.index'))
            ->assertInertia(fn ($page) => $page
                ->component('hr/documents/document-acknowledgments/index')
                ->where('statusCounts', ['all' => 2, 'pending' => 0, 'acknowledged' => 1, 'overdue' => 1, 'exempted' => 0])
                ->has('users', 2)
                ->has('documents', 1));

        $this->get(route('hr.documents.document-acknowledgments.index', ['status' => 'overdue']))
            ->assertInertia(fn ($page) => $page->has('documentAcknowledgments.data', 1)->where('documentAcknowledgments.data.0.user_id', $bob->id));

        $pending = DocumentAcknowledgment::where('user_id', $bob->id)->firstOrFail();
        $edit = ['document_id' => $document->id, 'user_id' => $bob->id];
        $this->put(route('hr.documents.document-acknowledgments.update', $pending), [...$edit, 'status' => 'overdue'])->assertSessionHasErrors('status');
        // Moving it onto Alice would duplicate her acknowledgment of the same document.
        $this->put(route('hr.documents.document-acknowledgments.update', $pending), [...$edit, 'user_id' => $alice->id, 'status' => 'pending'])->assertSessionHasErrors('user_id');
        $this->put(route('hr.documents.document-acknowledgments.update', $pending), [...$edit, 'status' => 'exempted'])->assertSessionHasNoErrors();
        $this->assertSame('exempted', $pending->fresh()->status);

        $this->put(route('hr.documents.document-acknowledgments.update', $pending), [...$edit, 'status' => 'acknowledged', 'acknowledgment_note' => 'Read in person'])->assertSessionHasNoErrors();
        $this->assertNotNull($pending->fresh()->acknowledged_at);
        $this->assertSame('Read in person', $pending->fresh()->acknowledgment_note);

        $this->delete(route('hr.documents.document-acknowledgments.destroy', $pending));
        $this->assertModelMissing($pending);
    }

    public function test_employees_see_and_acknowledge_only_their_own()
    {
        $employee = $this->employeeUser();
        $other = $this->employeeUser();
        $document = $this->document();
        $mine = DocumentAcknowledgment::create(['document_id' => $document->id, 'user_id' => $employee->id]);
        $theirs = DocumentAcknowledgment::create(['document_id' => $document->id, 'user_id' => $other->id]);

        $this->actingAs($employee)
            ->get(route('hr.documents.document-acknowledgments.index'))
            ->assertInertia(fn ($page) => $page
                ->has('documentAcknowledgments.data', 1)
                ->where('documentAcknowledgments.data.0.id', $mine->id)
                ->where('users', [])
                ->where('documents', []));

        $this->put(route('hr.documents.document-acknowledgments.acknowledge', $theirs))->assertForbidden();
        $this->put(route('hr.documents.document-acknowledgments.acknowledge', $mine), ['acknowledgment_note' => 'Read it.'], ['User-Agent' => 'TestBrowser'])
            ->assertSessionHasNoErrors();

        $mine->refresh();
        $this->assertSame('acknowledged', $mine->status);
        $this->assertNotNull($mine->acknowledged_at);
        $this->assertSame('Read it.', $mine->acknowledgment_note);
        $this->assertSame('127.0.0.1', $mine->ip_address);
        $this->assertSame('pending', $theirs->fresh()->status);

        $this->put(route('hr.documents.document-acknowledgments.acknowledge', $mine))->assertForbidden();

        $this->post(route('hr.documents.document-acknowledgments.store'), ['document_id' => $document->id])->assertForbidden();
        $this->put(route('hr.documents.document-acknowledgments.update', $mine), ['status' => 'pending'])->assertForbidden();
        $this->delete(route('hr.documents.document-acknowledgments.destroy', $theirs))->assertForbidden();
    }
}
