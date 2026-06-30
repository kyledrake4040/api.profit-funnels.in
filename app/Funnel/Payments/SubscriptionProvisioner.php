<?php

declare(strict_types=1);

namespace App\Funnel\Payments;

use App\Mail\WelcomeEmail;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Turns a completed Stripe subscription checkout into a first-class account:
 * finds (or creates) the paying customer and records an active Subscription.
 *
 * This is the bridge between the public Maritime Geo checkout (which only knows
 * Stripe) and the SaaS API layer (User → Subscription → Plan). It is invoked
 * from the Stripe webhook so provisioning happens the moment payment clears.
 */
final class SubscriptionProvisioner
{
    /** Gateway tag stored on the Subscription so renewals/cancellations can be reconciled. */
    public const GATEWAY = 'stripe';

    /**
     * Provision from a `checkout.session.completed` session object.
     *
     * Returns the Subscription (existing or freshly created), or null when the
     * session isn't a subscription we can map — a one-time payment, a missing
     * plan/email, or an unknown plan slug. Idempotent on the Stripe subscription
     * id, so Stripe's retries never double-provision.
     *
     * @param array<string,mixed> $session
     */
    public function provisionFromSession(array $session): ?Subscription
    {
        if (($session['mode'] ?? null) !== 'subscription') {
            return null;
        }

        $planSlug = data_get($session, 'metadata.plan');
        $email    = data_get($session, 'customer_details.email') ?? ($session['customer_email'] ?? null);

        if (! is_string($planSlug) || ! is_string($email) || $email === '') {
            return null;
        }

        $plan = Plan::where('slug', $planSlug)->first();

        if ($plan === null || ! $plan->isActive()) {
            return null;
        }

        $reference = (string) ($session['subscription'] ?? $session['id'] ?? '');

        if ($reference !== '') {
            $existing = Subscription::withTrashed()
                ->where('gateway', self::GATEWAY)
                ->where('gateway_reference', $reference)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => (string) (data_get($session, 'customer_details.name') ?: Str::before($email, '@')),
                'password' => Hash::make(Str::random(40)),
            ],
        );

        // Send a welcome email only to brand-new users so they know how to log in.
        if ($user->wasRecentlyCreated) {
            Mail::to($user->email)->queue(new WelcomeEmail($user));
        }

        $startsAt = Carbon::now();

        return $user->subscriptions()->create([
            'plan_id'           => $plan->id,
            'status'            => config('custom.subscription.status_active'),
            'gateway'           => self::GATEWAY,
            'gateway_reference' => $reference !== '' ? $reference : null,
            'starts_at'         => $startsAt,
            'ends_at'           => $plan->periodEndFrom($startsAt),
        ]);
    }
}
