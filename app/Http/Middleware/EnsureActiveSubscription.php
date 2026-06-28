<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate business-feature routes to users who either have an active subscription
 * or are still within the free-trial window (config('funnel.trial_days')).
 *
 * Dev/test bypass: when APP_ENV=testing or SKIP_SUBSCRIPTION_CHECK=true, the
 * check is skipped so tests don't need to seed subscriptions for every feature.
 */
final class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('funnel.skip_subscription_check')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Active subscription — let them through.
        if ($user->subscriptions()->where('status', config('custom.subscription.status_active'))->where(function ($q) {
            $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
        })->exists()) {
            return $next($request);
        }

        // Within free trial window — let them through.
        $trialDays = (int) config('funnel.trial_days', 8);
        if ($trialDays > 0 && $user->created_at->addDays($trialDays)->isFuture()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Your free trial has ended. Please subscribe to continue using ProfitProof.',
            'subscribe_url' => url('/#pricing'),
        ], 402);
    }
}
