<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\DocumentAcknowledgment;
use App\Models\Employee;
use App\Models\HrDocument;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DocumentAcknowledgmentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $search = addcslashes(trim($request->string('search')->toString()), '%_\\');
        $canManageAny = $user->can('manage-any-document-acknowledgments');

        $query = DocumentAcknowledgment::query()
            ->visibleTo($user)
            ->with('document:id,title,version,file_name', 'user:id,name,email,avatar_path', 'assigner:id,name,email,avatar_path')
            ->when($request->integer('document_id'), fn ($q, $id) => $q->where('document_id', $id))
            ->when($request->integer('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('document', fn ($d) => $d->where('title', 'like', "%{$search}%"))
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))));

        $statusCounts = ['all' => (clone $query)->count()]
            + collect(DocumentAcknowledgment::STATUSES)->mapWithKeys(fn ($s) => [$s => (clone $query)->whereStatus($s)->count()])->all();

        $query->when(in_array($request->input('status'), DocumentAcknowledgment::STATUSES, true), fn ($q) => $q->whereStatus($request->input('status')));

        return Inertia::render('hr/documents/document-acknowledgments/index', [
            'documentAcknowledgments' => TableQuery::paginate($query, $request, [], ['due_date', 'acknowledged_at', 'status', 'created_at']),
            'statusCounts' => $statusCounts,
            'documents' => $canManageAny ? HrDocument::query()->where('requires_acknowledgment', true)->orderBy('title')->get(['id', 'title']) : [],
            'users' => $canManageAny ? $this->employeeUsers() : [],
            'filters' => TableQuery::filters($request, ['status', 'document_id', 'user_id']),
        ]);
    }

    /**
     * Assign a document to one employee, or to every employee when user_id is empty.
     */
    public function store(Request $request): RedirectResponse
    {
        $employeeUserIds = $this->employeeUsers()->pluck('id');
        $data = $request->validate([
            'document_id' => ['required', 'integer', Rule::exists('hr_documents', 'id')->where('requires_acknowledgment', true)],
            'user_id' => ['nullable', 'integer', Rule::in($employeeUserIds)],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $userIds = ($data['user_id'] ?? null) ? [$data['user_id']] : $employeeUserIds->all();

        foreach ($userIds as $userId) {
            DocumentAcknowledgment::query()->firstOrCreate(
                ['document_id' => $data['document_id'], 'user_id' => $userId],
                ['due_date' => $data['due_date'] ?? null, 'status' => 'pending', 'assigned_by' => $request->user()?->id],
            );
        }

        return $this->done(__('Acknowledgment requested successfully.'));
    }

    public function update(Request $request, DocumentAcknowledgment $documentAcknowledgment): RedirectResponse
    {
        abort_unless($documentAcknowledgment->isVisibleTo($this->user($request)), 404);

        $data = $request->validate([
            'document_id' => ['required', 'integer', Rule::exists('hr_documents', 'id')->where('requires_acknowledgment', true)],
            'user_id' => [
                'required', 'integer', Rule::in($this->employeeUsers()->pluck('id')),
                Rule::unique('document_acknowledgments')->where('document_id', $request->integer('document_id'))->ignore($documentAcknowledgment),
            ],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['required', Rule::in(DocumentAcknowledgment::STORED_STATUSES)],
            'acknowledgment_note' => ['nullable', 'string', 'max:1000'],
        ]);

        // Marking it acknowledged here keeps an existing acknowledgment time; any other status clears it.
        $data['acknowledged_at'] = $data['status'] === 'acknowledged' ? ($documentAcknowledgment->acknowledged_at ?? now()) : null;
        $documentAcknowledgment->update($data);

        return $this->done(__('Acknowledgment updated successfully.'));
    }

    /**
     * The assignee confirms they have read the document.
     */
    public function acknowledge(Request $request, DocumentAcknowledgment $documentAcknowledgment): RedirectResponse
    {
        abort_unless($documentAcknowledgment->user_id === $request->user()?->id, 403);
        abort_unless($documentAcknowledgment->getRawOriginal('status') === 'pending', 403, __('This document has already been acknowledged.'));

        $data = $request->validate(['acknowledgment_note' => ['nullable', 'string', 'max:1000']]);

        $documentAcknowledgment->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledgment_note' => $data['acknowledgment_note'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return $this->done(__('Document acknowledged.'));
    }

    public function destroy(Request $request, DocumentAcknowledgment $documentAcknowledgment): RedirectResponse
    {
        abort_unless($documentAcknowledgment->isVisibleTo($this->user($request)), 404);
        $documentAcknowledgment->delete();

        return $this->done(__('Acknowledgment deleted successfully.'));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    /**
     * Users with an employee profile, for the assign form and filter.
     *
     * @return Collection<int, User>
     */
    private function employeeUsers(): Collection
    {
        return User::query()->whereIn('id', Employee::query()->select('user_id'))->orderBy('name')->get(['id', 'name']);
    }
}
