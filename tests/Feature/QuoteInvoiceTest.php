<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class QuoteInvoiceTest extends TestCase
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
            'name'     => 'Acme',
            'slug'     => 'acme',
            'status'   => config('custom.agency.status_active'),
        ]);

        $this->account = $agency->accounts()->create([
            'name'   => 'Client',
            'slug'   => 'client',
            'status' => config('custom.account.status_active'),
        ]);

        Passport::actingAs($this->owner, ['*'], 'api');
    }

    public function test_quote_total_is_computed_from_line_items(): void
    {
        $response = $this->postJson("/api/accounts/{$this->account->id}/quotes", [
            'items' => [
                ['description' => 'Exterior paint', 'quantity' => 2, 'unit_price' => 1500],
                ['description' => 'Primer', 'quantity' => 3, 'unit_price' => 200],
            ],
        ])->assertCreated();

        // 2*1500 + 3*200 = 3600
        $this->assertSame('3600.00', $response->json('data.total'));
        $this->assertSame('Q-0001', $response->json('data.number'));
        $this->assertSame('Draft', $response->json('data.status'));
    }

    public function test_converting_a_quote_creates_an_invoice_with_the_same_items(): void
    {
        $quote = $this->postJson("/api/accounts/{$this->account->id}/quotes", [
            'items' => [['description' => 'Job', 'quantity' => 1, 'unit_price' => 4800]],
        ])->assertCreated()->json('data');

        $invoice = $this->postJson("/api/accounts/{$this->account->id}/quotes/{$quote['id']}/convert")
            ->assertCreated()
            ->assertJsonPath('data.total', '4800.00')
            ->assertJsonPath('data.status', 'Draft')
            ->assertJsonPath('data.quote_id', $quote['id'])
            ->json('data');

        $this->assertCount(1, $invoice['items']);
        // The quote is now Accepted.
        $this->assertDatabaseHas('quotes', ['id' => $quote['id'], 'status' => 'Accepted']);
        $this->assertSame('INV-0001', $invoice['number']);
    }

    public function test_marking_an_invoice_paid_sets_status_and_timestamp(): void
    {
        $invoice = $this->postJson("/api/accounts/{$this->account->id}/invoices", [
            'items' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 1000]],
        ])->assertCreated()->json('data');

        $this->postJson("/api/accounts/{$this->account->id}/invoices/{$invoice['id']}/pay")
            ->assertOk()
            ->assertJsonPath('data.status', 'Paid');

        $this->assertNotNull(\App\Models\Invoice::find($invoice['id'])->paid_at);
    }

    public function test_paid_invoices_feed_the_dashboard_revenue(): void
    {
        $paid = $this->postJson("/api/accounts/{$this->account->id}/invoices", [
            'items' => [['description' => 'A', 'quantity' => 1, 'unit_price' => 2500]],
        ])->json('data');
        $this->postJson("/api/accounts/{$this->account->id}/invoices/{$paid['id']}/pay")->assertOk();

        // A second, unpaid invoice → outstanding.
        $this->postJson("/api/accounts/{$this->account->id}/invoices", [
            'items' => [['description' => 'B', 'quantity' => 1, 'unit_price' => 800]],
        ])->assertCreated();

        $dash = $this->getJson("/api/accounts/{$this->account->id}/dashboard")->assertOk();
        $this->assertSame(2500, (int) $dash->json('data.invoices.paid_total'));
        $this->assertSame(800, (int) $dash->json('data.invoices.outstanding_total'));
    }

    public function test_quote_with_a_foreign_contact_is_rejected(): void
    {
        $otherAgency = Agency::create(['owner_id' => $this->owner->id, 'name' => 'A2', 'slug' => 'a2', 'status' => 'Active']);
        $otherAccount = $otherAgency->accounts()->create(['name' => 'Other', 'slug' => 'other', 'status' => 'Active']);
        $foreign = $otherAccount->contacts()->create(['first_name' => 'Foreign']);

        $this->postJson("/api/accounts/{$this->account->id}/quotes", [
            'contact_id' => $foreign->id,
            'items'      => [['description' => 'X', 'quantity' => 1, 'unit_price' => 1]],
        ])->assertStatus(422);
    }

    public function test_quotes_are_member_guarded(): void
    {
        $outsider = User::create(['name' => 'Out', 'email' => 'out@example.com', 'password' => bcrypt('secret-secret')]);
        Passport::actingAs($outsider, ['*'], 'api');

        $this->getJson("/api/accounts/{$this->account->id}/quotes")->assertForbidden();
    }
}
