<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PlanController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $plans = Plan::where('status', config('custom.plan.status_active'))
            ->orderBy('price')
            ->get();

        return $this->successResponse($plans);
    }

    public function show(mixed $plan): JsonResponse
    {
        $model = Plan::where('status', config('custom.plan.status_active'))->find($plan);

        if ($model === null) {
            return $this->errorResponse(__('Plan not found.'), 404);
        }

        return $this->successResponse($model);
    }
}
