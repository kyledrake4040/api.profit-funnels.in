<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AccountRequest;
use App\Models\Account;
use App\Models\Agency;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Client sub-accounts. Visibility is tenancy-scoped: a user sees the accounts
 * they're a member of plus every account under an agency they own. Creating an
 * account requires owning the target agency.
 */
final class AccountController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $accounts = Account::query()
            ->where(function ($query) use ($user) {
                $query->whereHas('agency', fn ($a) => $a->where('owner_id', $user->id))
                    ->orWhereHas('members', fn ($m) => $m->where('users.id', $user->id));
            })
            ->with('agency')
            ->latest()
            ->get();

        return $this->successResponse($accounts);
    }

    public function store(AccountRequest $request): JsonResponse
    {
        $agency = Agency::find($request->input('agency_id'));

        // Only the agency's owner may provision sub-accounts under it.
        if ($agency === null || ! $request->user()->ownsAgency($agency)) {
            return $this->errorResponse(__('You do not own this agency.'), 403);
        }

        $account = $agency->accounts()->create([
            'name'   => $request->input('name'),
            'slug'   => $this->uniqueSlug((string) $request->input('name')),
            'status' => config('custom.account.status_active'),
        ]);

        return $this->successResponse($account->load('agency'), __('Account created.'), 201);
    }

    /**
     * Access is enforced by the account.member middleware, which also resolves
     * the {account} route parameter to the model.
     */
    public function show(Account $account): JsonResponse
    {
        return $this->successResponse($account->load(['agency', 'members']));
    }

    private function uniqueSlug(string $name): string
    {
        $base   = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug   = $base;
        $suffix = 1;

        while (Account::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
