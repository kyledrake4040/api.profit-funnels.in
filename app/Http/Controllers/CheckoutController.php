<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Funnel\Payments\StripePaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Turns the Maritime Geo pricing buttons into real, recurring Stripe checkout.
 *
 * If Stripe isn't configured (no STRIPE_SECRET — e.g. local/dev), it degrades
 * gracefully to the lead form so the page is never a dead end.
 */
final class CheckoutController extends Controller
{
    public function start(string $plan): RedirectResponse
    {
        $plans = (array) config('funnel.plans', []);
        abort_unless(array_key_exists($plan, $plans), 404);

        $secret = (string) config('services.stripe.secret', '');
        if ($secret === '') {
            // Can't charge without keys — capture the lead instead of a dead end.
            return redirect(route('landing') . '#signup')->with('checkout_unavailable', $plan);
        }

        $link = (new StripePaymentGateway($secret))->createSubscriptionCheckout(
            'Maritime Geo ' . ucwords(str_replace('_', ' ', $plan)),
            (int) $plans[$plan],
            (string) config('funnel.currency', 'usd'),
            'month',
            route('checkout.success'),
            route('landing') . '#pricing',
            // Carry the plan slug through Stripe so the webhook can provision the
            // customer's account + subscription once payment completes. Config
            // keys use underscores ("done_for_you"); plan slugs use hyphens.
            ['plan' => str_replace('_', '-', $plan)],
        );

        return redirect()->away($link->url);
    }

    public function success(): View
    {
        return view('checkout.success');
    }
}
