<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ContactRequest;
use App\Models\Account;
use App\Models\Contact;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return $this->successResponse($contact, __('Contact created.'), 201);
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
