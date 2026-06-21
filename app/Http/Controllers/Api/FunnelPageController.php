<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FunnelPageRequest;
use App\Models\Funnel;
use App\Models\FunnelPage;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FunnelPageController extends Controller
{
    use ApiResponse;

    /**
     * List the pages of a funnel owned by the authenticated user.
     *
     * @param  Request  $request
     * @param  int  $funnel
     *
     * @return JsonResponse
     */
    public function index(Request $request, $funnel): JsonResponse
    {
        $funnelModel = $this->resolveFunnel($request, $funnel);

        if ($funnelModel === null) {
            return $this->errorResponse(__('Funnel not found.'), 404);
        }

        $pages = $funnelModel->pages()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->successResponse($pages);
    }

    /**
     * Create a page within a funnel.
     *
     * @param  FunnelPageRequest  $request
     * @param  int  $funnel
     *
     * @return JsonResponse
     */
    public function store(FunnelPageRequest $request, $funnel): JsonResponse
    {
        $funnelModel = $this->resolveFunnel($request, $funnel);

        if ($funnelModel === null) {
            return $this->errorResponse(__('Funnel not found.'), 404);
        }

        $page = $funnelModel->pages()->create([
            'name' => $request->input('name'),
            'slug' => $this->uniqueSlug($funnelModel, $request->input('name')),
            'type' => $request->input('type', config('custom.page.type_landing')),
            'content' => $request->input('content'),
            'sort_order' => $request->input('sort_order', 0),
            'status' => $request->input('status', config('custom.page.status_active')),
        ]);

        return $this->successResponse($page, __('Page created.'), 201);
    }

    /**
     * Show a single page within a funnel.
     *
     * @param  Request  $request
     * @param  int  $funnel
     * @param  int  $page
     *
     * @return JsonResponse
     */
    public function show(Request $request, $funnel, $page): JsonResponse
    {
        $pageModel = $this->resolvePage($request, $funnel, $page);

        if ($pageModel === null) {
            return $this->errorResponse(__('Page not found.'), 404);
        }

        return $this->successResponse($pageModel);
    }

    /**
     * Update a page within a funnel.
     *
     * @param  FunnelPageRequest  $request
     * @param  int  $funnel
     * @param  int  $page
     *
     * @return JsonResponse
     */
    public function update(FunnelPageRequest $request, $funnel, $page): JsonResponse
    {
        $pageModel = $this->resolvePage($request, $funnel, $page);

        if ($pageModel === null) {
            return $this->errorResponse(__('Page not found.'), 404);
        }

        $pageModel->fill([
            'name' => $request->input('name'),
            'type' => $request->input('type', $pageModel->type),
            'content' => $request->input('content'),
            'sort_order' => $request->input('sort_order', $pageModel->sort_order),
            'status' => $request->input('status', $pageModel->status),
        ]);
        $pageModel->save();

        return $this->successResponse($pageModel, __('Page updated.'));
    }

    /**
     * Soft delete a page within a funnel.
     *
     * @param  Request  $request
     * @param  int  $funnel
     * @param  int  $page
     *
     * @return JsonResponse
     */
    public function destroy(Request $request, $funnel, $page): JsonResponse
    {
        $pageModel = $this->resolvePage($request, $funnel, $page);

        if ($pageModel === null) {
            return $this->errorResponse(__('Page not found.'), 404);
        }

        $pageModel->delete();

        return $this->successResponse(null, __('Page deleted.'));
    }

    /**
     * Resolve a funnel owned by the authenticated user.
     *
     * @param  Request  $request
     * @param  int  $funnel
     *
     * @return Funnel|null
     */
    private function resolveFunnel(Request $request, $funnel): ?Funnel
    {
        return $request->user()->funnels()->find($funnel);
    }

    /**
     * Resolve a page belonging to a funnel owned by the authenticated user.
     *
     * @param  Request  $request
     * @param  int  $funnel
     * @param  int  $page
     *
     * @return FunnelPage|null
     */
    private function resolvePage(Request $request, $funnel, $page): ?FunnelPage
    {
        $funnelModel = $this->resolveFunnel($request, $funnel);

        if ($funnelModel === null) {
            return null;
        }

        return $funnelModel->pages()->find($page);
    }

    /**
     * Generate a slug unique within the given funnel.
     *
     * @param  Funnel  $funnel
     * @param  string  $name
     *
     * @return string
     */
    private function uniqueSlug(Funnel $funnel, string $name): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 1;

        while ($funnel->pages()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
