<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Agency;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;
    private Quote $quote;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::create([
            'name'     => 'Owner',
            'email'    => 'owner@example.com',
            'password' => bcrypt('secret'),
        ]);

        $agency = Agency::create([
            'owner_id' => $owner->id,
            'name'     => 'Painters',
            'slug'     => 'painters',
            'status'   => config('custom.agency.status_active'),
        ]);

        $this->account = $agency->accounts()->create([
            'name'   => 'Gulf Coast Painting',
            'slug'   => 'gulf-coast',
            'status' => config('custom.account.status_active'),
        ]);

        $this->quote = $this->account->quotes()->create([
            'number'   => 'Q-0001',
            'status'   => config('custom.quote.status_sent'),
            'currency' => 'cad',
        ]);
        $this->quote->items()->create(['description' => 'Exterior painting', 'quantity' => 1, 'unit_price' => 4500]);
        $this->quote->recalculateTotal();
    }

    // -------------------------------------------------------------------------
    // Token auto-generation
    // -------------------------------------------------------------------------

    public function test_quote_receives_accept_token_on_creation(): void
    {
        $this->assertNotEmpty($this->quote->accept_token);
        $this->assertGreaterThanOrEqual(40, strlen($this->quote->accept_token));
    }

    // -------------------------------------------------------------------------
    // Show page
    // -------------------------------------------------------------------------

    public function test_quote_show_page_renders_with_valid_token(): void
    {
        $this->get(route('quote.show', $this->quote->accept_token))
            ->assertOk()
            ->assertSee($this->quote->number)
            ->assertSee('Exterior painting')
            ->assertSee('Accept this quote');
    }

    public function test_quote_show_page_returns_404_for_unknown_token(): void
    {
        $this->get(route('quote.show', 'not-a-real-token'))->assertNotFound();
    }

    public function test_already_accepted_quote_shows_accepted_state(): void
    {
        $this->quote->status = config('custom.quote.status_accepted');
        $this->quote->save();

        $this->get(route('quote.show', $this->quote->accept_token))
            ->assertOk()
            ->assertSee('You have accepted this quote')
            ->assertDontSee('Accept this quote');
    }

    // -------------------------------------------------------------------------
    // Accept action
    // -------------------------------------------------------------------------

    public function test_client_can_accept_a_quote(): void
    {
        $this->post(route('quote.accept', $this->quote->accept_token))
            ->assertRedirect(route('quote.success', $this->quote->accept_token));

        $this->assertDatabaseHas('quotes', [
            'id'     => $this->quote->id,
            'status' => config('custom.quote.status_accepted'),
        ]);
    }

    public function test_accepting_an_already_accepted_quote_redirects_to_success(): void
    {
        $this->quote->status = config('custom.quote.status_accepted');
        $this->quote->save();

        $this->post(route('quote.accept', $this->quote->accept_token))
            ->assertRedirect(route('quote.success', $this->quote->accept_token));
    }

    public function test_accept_returns_404_for_unknown_token(): void
    {
        $this->post(route('quote.accept', 'bad-token'))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Success page
    // -------------------------------------------------------------------------

    public function test_success_page_renders_after_acceptance(): void
    {
        $this->quote->status = config('custom.quote.status_accepted');
        $this->quote->save();

        $this->get(route('quote.success', $this->quote->accept_token))
            ->assertOk()
            ->assertSee('Quote accepted')
            ->assertSee($this->quote->number);
    }

    // -------------------------------------------------------------------------
    // Console: accept link in API response
    // -------------------------------------------------------------------------

    public function test_quotes_api_includes_accept_token(): void
    {
        $owner  = User::where('email', 'owner@example.com')->first();
        \Laravel\Passport\Passport::actingAs($owner, ['*'], 'api');

        $this->getJson("/api/accounts/{$this->account->id}/quotes")
            ->assertOk()
            ->assertJsonFragment(['accept_token' => $this->quote->accept_token]);
    }
}
