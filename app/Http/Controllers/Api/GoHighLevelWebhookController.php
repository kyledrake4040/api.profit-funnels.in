<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Funnel\Attribution\AttributionRecorder;
use App\Funnel\Attribution\JsonAttributionStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound GoHighLevel webhook. GHL posts here whenever a new lead/contact is
 * captured; leads carrying utm_source=funnel are attributed to the funnel.
 *
 * Thin adapter: all logic lives in the framework-free AttributionRecorder so
 * it can be unit-tested without booting the app.
 */
final class GoHighLevelWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $recorder = new AttributionRecorder(
            new JsonAttributionStore(base_path('storage/funnel/attribution.json'))
        );

        $row = $recorder->recordLead($request->all());

        return response()->json([
            'recorded' => true,
            'id' => $row->id,
            'attributed_to_funnel' => $row->isFunnel(),
        ]);
    }
}
