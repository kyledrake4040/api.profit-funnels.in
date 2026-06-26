<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Ai\ClaudeClient;
use App\Ai\LeadReplyDrafter;
use App\Automation\AutomationEngine;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ContactRequest;
use App\Models\Account;
use App\Models\Contact;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRM contacts, nested under and scoped to an account. The account.member
 * middleware authorizes access and resolves the {account} route parameter to the
 * model, so every query here is already tenant-scoped.
 */
final class ContactController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $contacts = $this->account($request)->contacts()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('q'), function ($q, $term) {
                $q->where(function ($w) use ($term) {
                    $w->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('company', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->get();

        return $this->successResponse($contacts);
    }

    public function store(ContactRequest $request): JsonResponse
    {
        $contact = $this->account($request)->contacts()->create([
            'first_name'    => $request->input('first_name'),
            'last_name'     => $request->input('last_name'),
            'email'         => $request->input('email'),
            'phone'         => $request->input('phone'),
            'company'       => $request->input('company'),
            'status'        => $request->input('status', config('custom.contact.status_lead')),
            'source'        => $request->input('source'),
            'tags'          => $request->input('tags'),
            'custom_fields' => $request->input('custom_fields'),
            'notes'         => $request->input('notes'),
        ]);

        // Run any "contact.created" automations (tag, set status, create job…),
        // then reflect their effect in the response.
        app(AutomationEngine::class)->fire(
            config('custom.automation.event_contact_created'),
            $this->account($request),
            ['contact' => $contact],
        );

        return $this->successResponse($contact->fresh(), __('Contact created.'), 201);
    }

    public function show(Request $request): JsonResponse
    {
        $model = $this->resolveContact($request);

        if ($model === null) {
            return $this->errorResponse(__('Contact not found.'), 404);
        }

        return $this->successResponse($model);
    }

    public function update(ContactRequest $request): JsonResponse
    {
        $model = $this->resolveContact($request);

        if ($model === null) {
            return $this->errorResponse(__('Contact not found.'), 404);
        }

        $model->fill([
            'first_name'    => $request->input('first_name'),
            'last_name'     => $request->input('last_name'),
            'email'         => $request->input('email'),
            'phone'         => $request->input('phone'),
            'company'       => $request->input('company'),
            'status'        => $request->input('status', $model->status),
            'source'        => $request->input('source'),
            'tags'          => $request->input('tags'),
            'custom_fields' => $request->input('custom_fields'),
            'notes'         => $request->input('notes'),
        ]);
        $model->save();

        return $this->successResponse($model, __('Contact updated.'));
    }

    public function destroy(Request $request): JsonResponse
    {
        $model = $this->resolveContact($request);

        if ($model === null) {
            return $this->errorResponse(__('Contact not found.'), 404);
        }

        $model->delete();

        return $this->successResponse(null, __('Contact deleted.'));
    }

    /**
     * AI-draft the first reply to a lead. Degrades gracefully when no Claude API
     * key is configured (like Stripe checkout without a secret).
     */
    public function draftReply(Request $request): JsonResponse
    {
        $contact = $this->resolveContact($request);

        if ($contact === null) {
            return $this->errorResponse(__('Contact not found.'), 404);
        }

        if (! app(ClaudeClient::class)->isConfigured()) {
            return $this->errorResponse(
                __('AI replies are not set up yet. Add a Claude API key (CLAUDE_API_KEY) to enable them.'),
                422,
            );
        }

        try {
            $draft = app(LeadReplyDrafter::class)->draftFor($contact);
        } catch (Throwable $e) {
            return $this->errorResponse(__('Could not draft a reply right now. Please try again.'), 502);
        }

        return $this->successResponse(['draft' => $draft]);
    }

    private function account(Request $request): Account
    {
        $account = $request->route('account');

        return $account instanceof Account ? $account : Account::findOrFail($account);
    }

    private function resolveContact(Request $request): ?Contact
    {
        return $this->account($request)->contacts()->find($request->route('contact'));
    }
}
