<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Timestamped notes on a contact (the activity log). Notes are scoped through
 * the account → contact chain so the account.member middleware already owns
 * the authorization; we just need to verify the contact belongs to the account.
 */
final class ContactNoteController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $contact = $this->resolveContact($request);

        if ($contact === null) {
            return $this->errorResponse(__('Contact not found.'), 404);
        }

        $notes = $contact->contactNotes()
            ->with('user:id,name')
            ->latest()
            ->get();

        return $this->successResponse($notes);
    }

    public function store(Request $request): JsonResponse
    {
        $contact = $this->resolveContact($request);

        if ($contact === null) {
            return $this->errorResponse(__('Contact not found.'), 404);
        }

        $request->validate(['body' => 'required|string|max:5000']);

        $note = $contact->contactNotes()->create([
            'user_id' => $request->user()?->id,
            'body'    => $request->input('body'),
        ]);

        return $this->successResponse($note->load('user:id,name'), __('Note added.'), 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $contact = $this->resolveContact($request);

        if ($contact === null) {
            return $this->errorResponse(__('Contact not found.'), 404);
        }

        $note = $contact->contactNotes()->find($request->route('note'));

        if ($note === null) {
            return $this->errorResponse(__('Note not found.'), 404);
        }

        $note->delete();

        return $this->successResponse(null, __('Note deleted.'));
    }

    private function resolveContact(Request $request): ?Contact
    {
        $account = $request->route('account');
        $account = $account instanceof Account ? $account : Account::findOrFail($account);

        return $account->contacts()->find($request->route('contact'));
    }
}
