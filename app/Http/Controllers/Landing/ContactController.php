<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ContactMessage::query()
            ->when(in_array($request->input('status'), ContactMessage::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')));

        return Inertia::render('contacts/index', [
            'contacts' => TableQuery::paginate($query, $request, ['name', 'email', 'subject'], ['name', 'email', 'subject', 'status', 'created_at']),
            'filters' => TableQuery::filters($request, ['status']),
            'statuses' => ContactMessage::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, ContactMessage $contact): RedirectResponse
    {
        $contact->update($request->validate(['status' => ['required', Rule::in(ContactMessage::STATUSES)]]));

        return $this->done(__('Contact status updated successfully.'));
    }

    /**
     * Email a reply to the person who wrote in, and mark the message as contacted.
     */
    public function reply(Request $request, ContactMessage $contact): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
        ]);

        try {
            Mail::raw($data['message'], fn ($mail) => $mail->to($contact->email, $contact->name)->subject($data['subject']));
        } catch (Throwable $e) {
            return $this->toast('error', __('Could not send the reply: :error', ['error' => $e->getMessage()]));
        }

        if ($contact->status === 'new') {
            $contact->update(['status' => 'contacted']);
        }

        return $this->done(__('Reply sent to :email.', ['email' => $contact->email]));
    }

    public function destroy(ContactMessage $contact): RedirectResponse
    {
        $contact->delete();

        return $this->done(__('Contact deleted successfully.'));
    }
}
