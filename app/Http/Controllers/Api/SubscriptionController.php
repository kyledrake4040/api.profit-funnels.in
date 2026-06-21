<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubscriptionRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SubscriptionController extends Controller
{
    use ApiResponse;

    /**
     * List the authenticated user's subscriptions.
     *
     * @param  Request  $request
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $subscriptions = $request->user()->subscriptions()
            ->with('plan')
            ->latest()
            ->get();

        return $this->successResponse($subscriptions);
    }

    /**
     * Subscribe the authenticated user to a plan.
     *
     * Note: this records the subscription and its billing period. Capturing an
     * actual payment must be performed by a payment gateway integration (e.g.
     * Stripe) before the subscription is marked active; the gateway and its
     * reference can be supplied here once that charge has been confirmed.
     *
     * @param  SubscriptionRequest  $request
     *
     * @return JsonResponse
     */
    public function store(SubscriptionRequest $request): JsonResponse
    {
        $plan = Plan::find($request->input('plan_id'));

        if ($plan === null || !$plan->isActive()) {
            return $this->errorResponse(__('The selected plan is not available.'), 422);
        }

        $existing = $request->user()->subscriptions()
            ->where('plan_id', $plan->id)
            ->where('status', config('custom.subscription.status_active'))
            ->first();

        if ($existing !== null) {
            return $this->errorResponse(__('You already have an active subscription to this plan.'), 409);
        }

        $startsAt = Carbon::now();

        $subscription = $request->user()->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => config('custom.subscription.status_active'),
            'gateway' => $request->input('gateway'),
            'gateway_reference' => $request->input('gateway_reference'),
            'starts_at' => $startsAt,
            'ends_at' => $this->periodEnd($startsAt, $plan->interval),
        ]);

        return $this->successResponse(
            $subscription->load('plan'),
            __('Subscription activated.'),
            201
        );
    }

    /**
     * Cancel one of the authenticated user's subscriptions.
     *
     * @param  Request  $request
     * @param  int  $subscription
     *
     * @return JsonResponse
     */
    public function cancel(Request $request, $subscription): JsonResponse
    {
        $model = $request->user()->subscriptions()->find($subscription);

        if ($model === null) {
            return $this->errorResponse(__('Subscription not found.'), 404);
        }

        if ($model->status === config('custom.subscription.status_cancelled')) {
            return $this->errorResponse(__('Subscription is already cancelled.'), 409);
        }

        $model->status = config('custom.subscription.status_cancelled');
        $model->cancelled_at = Carbon::now();
        $model->save();

        return $this->successResponse($model, __('Subscription cancelled.'));
    }

    /**
     * Compute the end of a billing period for a given interval.
     *
     * @param  Carbon  $start
     * @param  string|null  $interval
     *
     * @return Carbon
     */
    private function periodEnd(Carbon $start, ?string $interval): Carbon
    {
        if ($interval === config('custom.plan.interval_yearly')) {
            return $start->copy()->addYear();
        }

        return $start->copy()->addMonth();
    }
}
