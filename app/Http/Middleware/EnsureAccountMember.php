<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Account;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenancy guard for any route carrying an {account} parameter. Confirms the
 * authenticated user may access that sub-account — either as a direct member or
 * as the owner of its agency — before the request reaches the controller.
 */
final class EnsureAccountMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $param   = $request->route('account');
        $account = $param instanceof Account ? $param : Account::find($param);

        if ($account === null) {
            abort(404);
        }

        $user = $request->user();

        if ($user === null || ! $user->canAccessAccount($account)) {
            abort(403);
        }

        // Hand the resolved model downstream so controllers don't re-query.
        $request->route()->setParameter('account', $account);

        return $next($request);
    }
}
