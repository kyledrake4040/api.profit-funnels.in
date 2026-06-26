<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Ai\ClaudeClient;
use App\Ai\DashboardAdvisor;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Per-account dashboard: a single roll-up of the account's CRM state — contact
 * mix, pipeline value (open vs won), and the most recent deals. Read-only, and
 * gated by the account.member middleware like the rest of the account routes.
 */
final class DashboardController extends Controller
{
    use ApiResponse;

    public function show(Request $request): JsonResponse
    {
        $account = $this->account($request);

        $open = config('custom.opportunity.status_open');
        $won  = config('custom.opportunity.status_won');
        $lost = config('custom.opportunity.status_lost');

        $contactsByStatus = $account->contacts()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $opportunities = $account->opportunities();

        $summary = [
            'contacts' => [
                'total'     => $account->contacts()->count(),
                'by_status' => $contactsByStatus,
            ],
            'opportunities' => [
                'open_count'  => (clone $opportunities)->where('status', $open)->count(),
                'open_value'  => (float) (clone $opportunities)->where('status', $open)->sum('value'),
                'won_count'   => (clone $opportunities)->where('status', $won)->count(),
                'won_value'   => (float) (clone $opportunities)->where('status', $won)->sum('value'),
                'lost_count'  => (clone $opportunities)->where('status', $lost)->count(),
            ],
            'invoices' => [
                'paid_total'        => (float) $account->invoices()->where('status', config('custom.invoice.status_paid'))->sum('total'),
                'outstanding_total' => (float) $account->invoices()->whereIn('status', [
                    config('custom.invoice.status_draft'),
                    config('custom.invoice.status_sent'),
                ])->sum('total'),
            ],
            'pipelines' => $account->pipelines()->count(),
            'recent_opportunities' => $account->opportunities()
                ->with(['stage:id,name', 'contact:id,first_name,last_name'])
                ->latest()
                ->limit(5)
                ->get(['id', 'name', 'value', 'currency', 'status', 'stage_id', 'contact_id', 'created_at']),
        ];

        return $this->successResponse($summary);
    }

    /**
     * AI "what to do today" read on the account's numbers. Degrades gracefully
     * when no Claude API key is configured.
     */
    public function insight(Request $request): JsonResponse
    {
        $account = $this->account($request);

        if (! app(ClaudeClient::class)->isConfigured()) {
            return $this->errorResponse(
                __('AI insights are not set up yet. Add a Claude API key (CLAUDE_API_KEY) to enable them.'),
                422,
            );
        }

        try {
            $insight = app(DashboardAdvisor::class)->adviseFor($account);
        } catch (Throwable $e) {
            return $this->errorResponse(__('Could not generate an insight right now. Please try again.'), 502);
        }

        return $this->successResponse(['insight' => $insight]);
    }

    private function account(Request $request): Account
    {
        $account = $request->route('account');

        return $account instanceof Account ? $account : Account::findOrFail($account);
    }
}
