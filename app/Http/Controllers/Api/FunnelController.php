<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FunnelRequest;
use App\Models\Funnel;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class FunnelController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $funnels = $request->user()->funnels()
            ->withCount('pages')
            ->latest()
            ->get();

        return $this->successResponse($funnels);
    }

    public function store(FunnelRequest $request): JsonResponse
    {
        $funnel = $request->user()->funnels()->create([
            'name'        => $request->input('name'),
            'slug'        => $this->uniqueSlug((string) $request->input('name')),
            'description' => $request->input('description'),
            'status'      => $request->input('status', config('custom.funnel.status_active')),
        ]);

        return $this->successResponse($funnel, __('Funnel created.'), 201);
    }

    public function show(Request $request, mixed $funnel): JsonResponse
    {
        $model = $request->user()->funnels()->with('pages')->find($funnel);

        if ($model === null) {
            return $this->errorResponse(__('Funnel not found.'), 404);
        }

        return $this->successResponse($model);
    }

    public function update(FunnelRequest $request, mixed $funnel): JsonResponse
    {
        $model = $request->user()->funnels()->find($funnel);

        if ($model === null) {
            return $this->errorResponse(__('Funnel not found.'), 404);
        }

        $model->fill([
            'name'        => $request->input('name'),
            'description' => $request->input('description'),
            'status'      => $request->input('status', $model->status),
        ]);
        $model->save();

        return $this->successResponse($model, __('Funnel updated.'));
    }

    public function destroy(Request $request, mixed $funnel): JsonResponse
    {
        $model = $request->user()->funnels()->find($funnel);

        if ($model === null) {
            return $this->errorResponse(__('Funnel not found.'), 404);
        }

        $model->delete();

        return $this->successResponse(null, __('Funnel deleted.'));
    }

    private function uniqueSlug(string $name): string
    {
        $base   = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug   = $base;
        $suffix = 1;

        while (Funnel::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
