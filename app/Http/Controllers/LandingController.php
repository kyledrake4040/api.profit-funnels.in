<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Funnel\Attribution\AttributionRecorder;
use App\Funnel\Attribution\AttributionStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Public sales site for the productized attribution service ("ProfitProof").
 *
 * Captured leads are recorded into the SAME attribution engine the product
 * sells, so the service tracks its own sales funnel and the dashboard doubles
 * as a live demo.
 */
final class LandingController extends Controller
{
    private const PLANS = ['starter', 'pro', 'done_for_you'];

    public function show(Request $request): View
    {
        return view('landing', [
            // Preserve attribution if traffic arrives with UTM tags.
            'utm' => [
                'source' => (string) $request->query('utm_source', ''),
                'medium' => (string) $request->query('utm_medium', ''),
                'campaign' => (string) $request->query('utm_campaign', ''),
            ],
        ]);
    }

    public function capture(Request $request, AttributionStore $store): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'business' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'plan' => ['nullable', 'in:' . implode(',', self::PLANS)],
            'utm_source' => ['nullable', 'string', 'max:80'],
            'utm_medium' => ['nullable', 'string', 'max:80'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
        ]);

        $payload = [
            'utm_source' => $data['utm_source'] ?? '' ?: 'website',
            'utm_medium' => $data['utm_medium'] ?? '',
            'utm_campaign' => $data['utm_campaign'] ?? ($data['plan'] ?? ''),
            'platform' => 'profitproof',
            'contact_id' => $data['email'],
            'name' => $data['name'],
        ];

        // Dogfood: record the lead into our own attribution engine.
        (new AttributionRecorder($store))->recordLead($payload);

        $this->forwardToCrm($data);

        return back()->with('lead_ok', $data['name']);
    }

    /**
     * Optionally push the lead to a CRM webhook. Never blocks the visitor.
     *
     * @param array<string,mixed> $data
     */
    private function forwardToCrm(array $data): void
    {
        $url = config('funnel.lead_forward_url');
        if (! $url) {
            return;
        }

        try {
            Http::asJson()->timeout(5)->post($url, $data);
        } catch (\Throwable $e) {
            Log::warning('Lead CRM forward failed.', ['error' => $e->getMessage()]);
        }
    }
}
