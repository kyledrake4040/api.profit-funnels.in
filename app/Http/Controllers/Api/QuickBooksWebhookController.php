<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Funnel\Attribution\AttributionRecorder;
use App\Funnel\Attribution\AttributionStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound QuickBooks webhook. Fires when an invoice is paid; we update the
 * revenue on the attribution row(s) for the matching lead/customer so the
 * funnel report can show revenue, not just leads.
 *
 * Thin adapter: logic lives in the framework-free AttributionRecorder.
 */
final class QuickBooksWebhookController extends Controller
{
    public function __invoke(Request $request, AttributionStore $store): JsonResponse
    {
        $updated = (new AttributionRecorder($store))->recordPaidInvoice($request->all());

        return response()->json([
            'updated_rows' => $updated,
        ]);
    }
}
