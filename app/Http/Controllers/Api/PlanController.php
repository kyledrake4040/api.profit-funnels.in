<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    use ApiResponse;

    /**
     * List all active subscription plans.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $plans = Plan::where('status', config('custom.plan.status_active'))
            ->orderBy('price')
            ->get();

        return $this->successResponse($plans);
    }

    /**
     * Show a single active plan.
     *
     * @param  int  $plan
     *
     * @return JsonResponse
     */
    public function show($plan): JsonResponse
    {
        $model = Plan::where('status', config('custom.plan.status_active'))->find($plan);

        if ($model === null) {
            return $this->errorResponse(__('Plan not found.'), 404);
        }

        return $this->successResponse($model);
    }
}
