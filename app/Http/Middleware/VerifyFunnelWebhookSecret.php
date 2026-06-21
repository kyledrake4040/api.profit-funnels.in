<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies inbound funnel webhooks against a shared secret so forged
 * leads/revenue can't be injected into attribution.
 *
 * The secret is accepted as an "X-Funnel-Token" header or a "?token=" query
 * parameter (GoHighLevel and QuickBooks can send either). When no secret is
 * configured the guard is a no-op, keeping local/dry-run setups frictionless —
 * set FUNNEL_WEBHOOK_SECRET in production to enforce it.
 */
final class VerifyFunnelWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('funnel.webhook_secret', '');

        if ($secret === '') {
            return $next($request);
        }

        $provided = (string) ($request->header('X-Funnel-Token') ?? '');
        if ($provided === '') {
            $provided = (string) $request->query('token', '');
        }

        if ($provided === '' || ! hash_equals($secret, $provided)) {
            return response()->json(['error' => 'invalid or missing webhook secret'], 401);
        }

        return $next($request);
    }
}
