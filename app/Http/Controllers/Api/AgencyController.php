<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AgencyRequest;
use App\Models\Agency;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Reseller agencies owned by the authenticated user. An agency is the top of the
 * tenancy tree; its owner provisions and bills sub-accounts.
 */
final class AgencyController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $agencies = $request->user()->ownedAgencies()
            ->withCount('accounts')
            ->latest()
            ->get();

        return $this->successResponse($agencies);
    }

    public function store(AgencyRequest $request): JsonResponse
    {
        $agency = $request->user()->ownedAgencies()->create([
            'name'          => $request->input('name'),
            'slug'          => $this->uniqueSlug((string) $request->input('name')),
            'brand_name'    => $request->input('brand_name'),
            'custom_domain' => $request->input('custom_domain'),
            'primary_color' => $request->input('primary_color'),
            'logo_url'      => $request->input('logo_url'),
            'status'        => config('custom.agency.status_active'),
        ]);

        return $this->successResponse($agency, __('Agency created.'), 201);
    }

    private function uniqueSlug(string $name): string
    {
        $base   = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug   = $base;
        $suffix = 1;

        while (Agency::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
