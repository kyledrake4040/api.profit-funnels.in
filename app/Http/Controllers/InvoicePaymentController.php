<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Funnel\Payments\StripePaymentGateway;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Public, no-login invoice payment. The business shares the invoice's pay link
 * (/pay/{token}); the client opens it, sees what they owe, and pays online via
 * Stripe Checkout. The Stripe webhook flips the invoice to Paid.
 *
 * If Stripe isn't configured (no STRIPE_SECRET — e.g. local/dev), the pay page
 * still renders; the Pay button degrades to a friendly "not available yet"
 * notice instead of a dead end.
 */
final class InvoicePaymentController extends Controller
{
    public function show(string $token): View
    {
        $invoice = $this->resolve($token);

        return view('pay.show', [
            'invoice'      => $invoice,
            'businessName' => $this->businessName($invoice),
            'configured'   => (string) config('services.stripe.secret', '') !== '',
        ]);
    }

    public function checkout(string $token): RedirectResponse
    {
        $invoice = $this->resolve($token);

        if ($invoice->isPaid()) {
            return redirect()->route('pay.success', $token);
        }

        $secret = (string) config('services.stripe.secret', '');
        if ($secret === '') {
            // No keys — don't leave the client at a dead end.
            return redirect()->route('pay.show', $token)->with('pay_unavailable', true);
        }

        $link = (new StripePaymentGateway($secret))->createCheckout(
            $this->businessName($invoice) . ' — Invoice ' . $invoice->number,
            (int) round((float) $invoice->total * 100),
            (string) ($invoice->currency ?: config('funnel.currency', 'usd')),
            route('pay.success', $token),
            route('pay.show', $token),
            // Carry the invoice id through Stripe so the webhook can mark it paid.
            ['invoice_id' => $invoice->id],
        );

        return redirect()->away($link->url);
    }

    public function success(string $token): View
    {
        $invoice = $this->resolve($token);

        return view('pay.success', [
            'invoice'      => $invoice,
            'businessName' => $this->businessName($invoice),
        ]);
    }

    private function resolve(string $token): Invoice
    {
        return Invoice::where('pay_token', $token)->firstOrFail();
    }

    private function businessName(Invoice $invoice): string
    {
        $account = $invoice->account;

        return $account?->site?->business_name ?: ($account?->name ?? config('app.name'));
    }
}
