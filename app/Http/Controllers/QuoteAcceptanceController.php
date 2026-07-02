<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public, no-login quote acceptance. The business shares the quote's accept link
 * (/quote/{token}); the client opens it, reviews what's proposed, and clicks
 * Accept to confirm. Declined or already-accepted quotes degrade gracefully.
 */
final class QuoteAcceptanceController extends Controller
{
    public function show(string $token): View
    {
        $quote = $this->resolve($token);

        return view('quote.show', [
            'quote'        => $quote->load(['items', 'contact']),
            'businessName' => $this->businessName($quote),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $quote = $this->resolve($token);

        if ($quote->isAccepted()) {
            return redirect()->route('quote.success', $token);
        }

        $quote->status = config('custom.quote.status_accepted');
        $quote->save();

        return redirect()->route('quote.success', $token);
    }

    public function success(string $token): View
    {
        $quote = $this->resolve($token);

        return view('quote.success', [
            'quote'        => $quote,
            'businessName' => $this->businessName($quote),
        ]);
    }

    private function resolve(string $token): Quote
    {
        return Quote::where('accept_token', $token)->firstOrFail();
    }

    private function businessName(Quote $quote): string
    {
        $account = $quote->account;

        return $account?->site?->business_name ?: ($account?->name ?? config('app.name'));
    }
}
