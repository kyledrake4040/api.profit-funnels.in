<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agency;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::create([
            'name'     => 'Owner',
            'email'    => 'owner@example.com',
            'password' => bcrypt('secret-secret'),
        ]);

        $agency = Agency::create([
            'owner_id' => $owner->id,
            'name'     => 'Acme Painting',
            'slug'     => 'acme',
            'status'   => config('custom.agency.status_active'),
        ]);

        $this->account = $agency->accounts()->create([
            'name'   => 'Acme Painting',
            'slug'   => 'acme-painting',
            'status' => config('custom.account.status_active'),
        ]);
    }

    private function makeInvoice(int $unitPrice = 1500): Invoice
    {
        $invoice = $this->account->invoices()->create([
            'number'   => 'INV-0001',
            'status'   => config('custom.invoice.status_sent'),
            'currency' => 'cad',
        ]);
        $invoice->items()->create(['description' => 'Exterior paint', 'quantity' => 1, 'unit_price' => $unitPrice]);
        $invoice->recalculateTotal();

        return $invoice;
    }

    public function test_a_pay_token_is_generated_on_create(): void
    {
        $invoice = $this->makeInvoice();

        $this->assertNotEmpty($invoice->pay_token);
        $this->assertSame('http://localhost/pay/' . $invoice->pay_token, $invoice->publicUrl());
    }

    public function test_public_pay_page_renders_the_invoice(): void
    {
        $invoice = $this->makeInvoice(2500);

        $this->get('/pay/' . $invoice->pay_token)
            ->assertOk()
            ->assertSee('Acme Painting')
            ->assertSee('INV-0001')
            ->assertSee('Exterior paint')
            ->assertSee('CAD 2,500.00');
    }

    public function test_unknown_pay_token_404s(): void
    {
        $this->get('/pay/nope-not-a-real-token')->assertNotFound();
    }

    public function test_checkout_without_stripe_configured_falls_back_gracefully(): void
    {
        config(['services.stripe.secret' => '']);
        $invoice = $this->makeInvoice();

        $this->get('/pay/' . $invoice->pay_token . '/checkout')
            ->assertRedirect(route('pay.show', $invoice->pay_token))
            ->assertSessionHas('pay_unavailable');

        // Still unpaid — nothing was charged.
        $this->assertFalse($invoice->fresh()->isPaid());
    }

    public function test_paid_invoice_checkout_redirects_to_success(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->markPaid();

        $this->get('/pay/' . $invoice->pay_token . '/checkout')
            ->assertRedirect(route('pay.success', $invoice->pay_token));
    }

    public function test_success_page_renders(): void
    {
        $invoice = $this->makeInvoice();

        $this->get('/pay/' . $invoice->pay_token . '/success')
            ->assertOk()
            ->assertSee('payment received');
    }

    public function test_stripe_webhook_marks_the_invoice_paid_from_metadata(): void
    {
        Queue::fake();
        $secret = 'whsec_test_secret';
        config(['services.stripe.webhook_secret' => $secret]);

        $invoice = $this->makeInvoice();
        $this->assertFalse($invoice->isPaid());

        $event = [
            'id'   => 'evt_inv_1',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id'             => 'cs_inv_1',
                'payment_intent' => 'pi_inv_1',
                'amount_total'   => 150000,
                'currency'       => 'cad',
                'payment_status' => 'paid',
                'metadata'       => ['invoice_id' => $invoice->id],
                'customer_details' => ['email' => 'client@example.com', 'name' => 'Client'],
            ]],
        ];

        $body = json_encode($event);
        $ts = time();
        $sig = 't=' . $ts . ',v1=' . hash_hmac('sha256', $ts . '.' . $body, $secret);

        $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $sig,
            'CONTENT_TYPE'          => 'application/json',
        ], $body)->assertOk();

        $this->assertTrue($invoice->fresh()->isPaid());
        $this->assertNotNull($invoice->fresh()->paid_at);
    }
}
