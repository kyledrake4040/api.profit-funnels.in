<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Funnel\Attribution\AttributionReport;
use App\Funnel\Attribution\JsonAttributionStore;
use Illuminate\Http\Request;

/**
 * Browser dashboard for the funnel attribution report: leads & revenue split
 * by funnel vs. other over a selectable window. Backed by the same store the
 * webhooks write to and `bin/funnel report` reads.
 */
final class FunnelDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $days = max(1, min(365, (int) $request->query('days', '7')));

        $store = new JsonAttributionStore(base_path('storage/funnel/attribution.json'));
        $summary = (new AttributionReport($store))->summarize($days);

        if ($request->query('format') === 'json') {
            return response()->json($summary);
        }

        $totalLeads = $summary['funnel']['leads'] + $summary['other']['leads'];
        $share = $totalLeads > 0 ? (int) round($summary['funnel']['leads'] / $totalLeads * 100) : 0;

        return view('funnel.dashboard', [
            'days' => $days,
            'summary' => $summary,
            'share' => $share,
        ]);
    }
}
