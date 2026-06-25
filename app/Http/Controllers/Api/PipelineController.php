<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PipelineRequest;
use App\Models\Account;
use App\Models\Pipeline;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sales pipelines, nested under and scoped to an account. A pipeline is created
 * together with its ordered stages (defaults seeded when none are given).
 */
final class PipelineController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $pipelines = $this->account($request)->pipelines()
            ->with('stages')
            ->withCount('opportunities')
            ->latest()
            ->get();

        return $this->successResponse($pipelines);
    }

    public function store(PipelineRequest $request): JsonResponse
    {
        $account = $this->account($request);

        $stageNames = $request->input('stages') ?: config('custom.pipeline.default_stages');

        $pipeline = DB::transaction(function () use ($account, $request, $stageNames) {
            $pipeline = $account->pipelines()->create([
                'name' => $request->input('name'),
                'slug' => $this->uniqueSlug($account, (string) $request->input('name')),
            ]);

            foreach (array_values($stageNames) as $i => $name) {
                $pipeline->stages()->create(['name' => $name, 'sort_order' => $i]);
            }

            return $pipeline;
        });

        return $this->successResponse($pipeline->load('stages'), __('Pipeline created.'), 201);
    }

    public function show(Request $request): JsonResponse
    {
        $model = $this->account($request)->pipelines()
            ->with('stages')
            ->withCount('opportunities')
            ->find($request->route('pipeline'));

        if ($model === null) {
            return $this->errorResponse(__('Pipeline not found.'), 404);
        }

        return $this->successResponse($model);
    }

    public function destroy(Request $request): JsonResponse
    {
        $model = $this->account($request)->pipelines()->find($request->route('pipeline'));

        if ($model === null) {
            return $this->errorResponse(__('Pipeline not found.'), 404);
        }

        $model->delete();

        return $this->successResponse(null, __('Pipeline deleted.'));
    }

    private function account(Request $request): Account
    {
        $account = $request->route('account');

        return $account instanceof Account ? $account : Account::findOrFail($account);
    }

    private function uniqueSlug(Account $account, string $name): string
    {
        $base   = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug   = $base;
        $suffix = 1;

        while ($account->pipelines()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
