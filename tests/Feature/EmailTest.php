<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\InvoiceEmail;
use App\Mail\WelcomeEmail;
use App\Models\Account;
use App\Models\Agency;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\TestCase;

class EmailTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name'     => 'Owner',
            'email'    => 'owner@example.com',
            'password' => bcrypt('secret-secret'),
        ]);

        $agency = Agency::create([
            'owner_id' => $this->owner->id,
            'name'     => 'Painters',
            'slug'     => 'painters',
            'status'   => config('custom.agency.status_active'),
        ]);

        $this->account = $agency->accounts()->create([
            'name'   => 'Gulf Coast Painting',
            'slug'   => 'gulf-coast',
            'status' => config('custom.account.status_active'),
        ]);

        Passport::actingAs($this->owner, ['*'], 'api');
    }

    // -------------------------------------------------------------------------
    // Welcome email
    // -------------------------------------------------------------------------

    public function test_welcome_email_is_queued_for_new_users_on_stripe_provisioning(): void
    {
        Mail::fake();
        Queue::fake();
        $this->seed(PlanSeeder::class);

        $secret = 'whsec_test_secret';
        config(['services.stripe.webhook_secret' => $secret]);

        $event = [
            'id'   => 'evt_welcome_1',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id'               => 'cs_welcome_1',
                'mode'             => 'subscription',
                'subscription'     => 'sub_welcome_1',
                'payment_intent'   => 'pi_welcome_1',
                'amount_total'     => 9900,
                'currency'         => 'cad',
                'payment_status'   => 'paid',
                'metadata'         => ['plan' => 'starter'],
                'customer_details' => ['email' => 'newbiz@example.com', 'name' => 'New Biz'],
            ]],
        ];

        $body = json_encode($event);
        $ts = time();
        $sig = 't=' . $ts . ',v1=' . hash_hmac('sha256', $ts . '.' . $body, $secret);

        $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $sig,
            'CONTENT_TYPE'          => 'application/json',
        ], $body)->assertOk();

        Mail::assertQueued(WelcomeEmail::class, fn ($m) => $m->user->email === 'newbiz@example.com');
    }

    public function test_welcome_email_is_not_resent_for_existing_users(): void
    {
        Mail::fake();
        $this->seed(PlanSeeder::class);

        // Pre-create the user so firstOrCreate finds them.
        User::create(['name' => 'Existing', 'email' => 'existing@example.com', 'password' => bcrypt('x')]);

        $secret = 'whsec_test_secret';
        config(['services.stripe.webhook_secret' => $secret]);

        $event = [
            'id'   => 'evt_existing_1',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id'               => 'cs_existing_1',
                'mode'             => 'subscription',
                'subscription'     => 'sub_existing_1',
                'payment_intent'   => 'pi_existing_1',
                'amount_total'     => 9900,
                'currency'         => 'cad',
                'payment_status'   => 'paid',
                'metadata'         => ['plan' => 'starter'],
                'customer_details' => ['email' => 'existing@example.com', 'name' => 'Existing'],
            ]],
        ];

        $body = json_encode($event);
        $ts = time();
        $sig = 't=' . $ts . ',v1=' . hash_hmac('sha256', $ts . '.' . $body, $secret);

        $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $sig,
            'CONTENT_TYPE'          => 'application/json',
        ], $body)->assertOk();

        Mail::assertNotQueued(WelcomeEmail::class);
    }

    // -------------------------------------------------------------------------
    // Invoice email
    // -------------------------------------------------------------------------

    public function test_invoice_can_be_emailed_to_client(): void
    {
        Mail::fake();

        $contact = $this->account->contacts()->create([
            'first_name' => 'Jane',
            'last_name'  => 'Client',
            'email'      => 'jane@example.com',
        ]);

        $invoice = $this->account->invoices()->create([
            'contact_id' => $contact->id,
            'number'     => 'INV-0001',
            'status'     => config('custom.invoice.status_sent'),
            'currency'   => 'cad',
        ]);
        $invoice->items()->create(['description' => 'Painting', 'quantity' => 1, 'unit_price' => 3500]);
        $invoice->recalculateTotal();

        $this->postJson("/api/accounts/{$this->account->id}/invoices/{$invoice->id}/email")
            ->assertOk();

        Mail::assertQueued(InvoiceEmail::class, fn ($m) => $m->invoice->id === $invoice->id);
    }

    public function test_invoice_email_fails_when_contact_has_no_email(): void
    {
        Mail::fake();

        $contact = $this->account->contacts()->create(['first_name' => 'No Email']);

        $invoice = $this->account->invoices()->create([
            'contact_id' => $contact->id,
            'number'     => 'INV-0002',
            'status'     => config('custom.invoice.status_sent'),
            'currency'   => 'cad',
        ]);

        $this->postJson("/api/accounts/{$this->account->id}/invoices/{$invoice->id}/email")
            ->assertStatus(422);

        Mail::assertNothingQueued();
    }

    public function test_invoice_email_fails_when_invoice_has_no_contact(): void
    {
        Mail::fake();

        $invoice = $this->account->invoices()->create([
            'number'   => 'INV-0003',
            'status'   => config('custom.invoice.status_sent'),
            'currency' => 'cad',
        ]);

        $this->postJson("/api/accounts/{$this->account->id}/invoices/{$invoice->id}/email")
            ->assertStatus(422);

        Mail::assertNothingQueued();
    }

    // -------------------------------------------------------------------------
    // Trial / subscription enforcement
    // -------------------------------------------------------------------------

    public function test_user_within_trial_can_access_account_features(): void
    {
        config(['funnel.skip_subscription_check' => false]);

        // owner was just created — they are within the 8-day trial.
        $this->getJson("/api/accounts/{$this->account->id}/dashboard")->assertOk();
    }

    public function test_user_past_trial_with_no_subscription_gets_402(): void
    {
        config(['funnel.skip_subscription_check' => false]);

        // Backdate the user's created_at beyond the trial window.
        $this->owner->created_at = now()->subDays(9);
        $this->owner->save();

        $this->getJson("/api/accounts/{$this->account->id}/dashboard")->assertStatus(402);
    }

    public function test_user_past_trial_with_active_subscription_passes(): void
    {
        config(['funnel.skip_subscription_check' => false]);
        $this->seed(PlanSeeder::class);

        $this->owner->created_at = now()->subDays(9);
        $this->owner->save();

        $plan = \App\Models\Plan::where('slug', 'starter')->first();
        $this->owner->subscriptions()->create([
            'plan_id'   => $plan->id,
            'status'    => config('custom.subscription.status_active'),
            'gateway'   => 'stripe',
            'starts_at' => now()->subDays(9),
            'ends_at'   => now()->addDays(21),
        ]);

        $this->getJson("/api/accounts/{$this->account->id}/dashboard")->assertOk();
    }

    public function test_user_with_expired_subscription_gets_402(): void
    {
        config(['funnel.skip_subscription_check' => false]);
        $this->seed(PlanSeeder::class);

        $this->owner->created_at = now()->subDays(20);
        $this->owner->save();

        $plan = \App\Models\Plan::where('slug', 'starter')->first();
        $this->owner->subscriptions()->create([
            'plan_id'   => $plan->id,
            'status'    => config('custom.subscription.status_active'),
            'gateway'   => 'stripe',
            'starts_at' => now()->subDays(20),
            'ends_at'   => now()->subDay(),
        ]);

        $this->getJson("/api/accounts/{$this->account->id}/dashboard")->assertStatus(402);
    }
}
