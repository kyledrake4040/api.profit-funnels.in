<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SiteRequest;
use App\Models\Account;
use App\Models\Site;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Manage an account's micro-site (one per account). Account-scoped; the public
 * rendering + lead capture live in SitePublicController.
 */
final class SiteController extends Controller
{
    use ApiResponse;

    public function show(Request $request): JsonResponse
    {
        return $this->successResponse($this->account($request)->site);
    }

    /**
     * Create or update the account's site (upsert).
     */
    public function upsert(SiteRequest $request): JsonResponse
    {
        $account = $this->account($request);
        $site    = $account->site;

        $data = [
            'business_name' => $request->input('business_name'),
            'headline'      => $request->input('headline'),
            'about'         => $request->input('about'),
            'phone'         => $request->input('phone'),
            'email'         => $request->input('email'),
            'city'          => $request->input('city'),
            'services'      => $request->input('services'),
            'theme_color'   => $request->input('theme_color'),
            'published'     => $request->boolean('published'),
        ];

        if ($site === null) {
            $data['slug'] = $this->uniqueSlug((string) $request->input('business_name'));
            $site = $account->site()->create($data);
            $message = __('Site created.');
        } else {
            $site->fill($data)->save();
            $message = __('Site updated.');
        }

        return $this->successResponse($site->fresh(), $message);
    }

    private function uniqueSlug(string $name): string
    {
        $base   = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug   = $base;
        $suffix = 1;

        while (Site::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    private function account(Request $request): Account
    {
        $account = $request->route('account');

        return $account instanceof Account ? $account : Account::findOrFail($account);
    }
}
