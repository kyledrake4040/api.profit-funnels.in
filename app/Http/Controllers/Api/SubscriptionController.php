<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubscriptionRequest;
use App\Models\Plan;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class SubscriptionController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $subscriptions = $request->user()->subscriptions()
            ->with('plan')
            ->latest()
            ->get();

        return $this->successResponse($subscriptions);
    }

    /**
     * Subscribe to a plan. Payment must be captured separately via a gateway
     * (e.g. Stripe) before calling this endpoint; pass gateway + gateway_reference
     * once the charge is confirmed.
     */
    public function store(SubscriptionRequest $request): JsonResponse
    {
        $plan = Plan::find($request->input('plan_id'));

        if ($plan === null || ! $plan->isActive()) {
            return $this->errorResponse(__('The selected plan is not available.'), 422);
        }

        $existing = $request->user()->subscriptions()
            ->where('plan_id', $plan->id)
            ->where('status', config('custom.subscription.status_active'))
            ->first();

        if ($existing !== null) {
            return $this->errorResponse(__('You already have an active subscription to this plan.'), 409);
        }

        $startsAt     = Carbon::now();
        $subscription = $request->user()->subscriptions()->create([
            'plan_id'           => $plan->id,
            'status'            => config('custom.subscription.status_active'),
            'gateway'           => $request->input('gateway'),
            'gateway_reference' => $request->input('gateway_reference'),
            'starts_at'         => $startsAt,
            'ends_at'           => $this->periodEnd($startsAt, $plan->interval),
        ]);

        return $this->successResponse(
            $subscription->load('plan'),
            __('Subscription activated.'),
            201
        );
    }

    public function cancel(Request $request, mixed $subscription): JsonResponse
    {
        $model = $request->user()->subscriptions()->find($subscription);

        if ($model === null) {
            return $this->errorResponse(__('Subscription not found.'), 404);
        }

        if ($model->status === config('custom.subscription.status_cancelled')) {
            return $this->errorResponse(__('Subscription is already cancelled.'), 409);
        }

        $model->status       = config('custom.subscription.status_cancelled');
        $model->cancelled_at = Carbon::now();
        $model->save();

        return $this->successResponse($model, __('Subscription cancelled.'));
    }

    private function periodEnd(Carbon $start, ?string $interval): Carbon
    {
        if ($interval === config('custom.plan.interval_yearly')) {
            return $start->copy()->addYear();
        }

        return $start->copy()->addMonth();
    }
}
