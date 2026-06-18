<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HubSpotClient
{
    /**
     * Base URL for the HubSpot CRM API.
     */
    protected string $baseUrl = 'https://api.hubapi.com';

    /**
     * Private app access token.
     *
     * @var string|null
     */
    protected $token;

    public function __construct()
    {
        $this->token = config('services.hubspot.token');
    }

    /**
     * Whether the client is configured to make requests.
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return ! empty($this->token);
    }

    /**
     * Sync a successful payment into HubSpot as a contact + deal.
     *
     * Creates (or updates) the contact by email, opens a deal for the
     * purchase, and associates the two. Failures are logged and swallowed so
     * a HubSpot outage never breaks the Stripe webhook acknowledgement.
     *
     * @param  Payment  $payment
     *
     * @return void
     */
    public function syncPayment(Payment $payment): void
    {
        if (! $this->isConfigured()) {
            Log::info('HubSpot token not configured; skipping CRM sync.', ['payment_id' => $payment->id]);

            return;
        }

        if (empty($payment->customer_email)) {
            Log::warning('Payment has no email; skipping HubSpot sync.', ['payment_id' => $payment->id]);

            return;
        }

        try {
            $contactId = $this->upsertContact($payment);
            $dealId = $this->createDeal($payment);

            if ($contactId && $dealId) {
                $this->associateDealToContact($dealId, $contactId);
            }

            Log::info('HubSpot sync complete.', [
                'payment_id' => $payment->id,
                'contact_id' => $contactId,
                'deal_id' => $dealId,
            ]);
        } catch (\Throwable $e) {
            Log::error('HubSpot sync failed.', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create or update a contact, keyed on email.
     *
     * @param  Payment  $payment
     *
     * @return string|null  the contact id
     */
    protected function upsertContact(Payment $payment): ?string
    {
        $name = trim((string) $payment->customer_name);
        $first = $name !== '' ? explode(' ', $name)[0] : null;
        $last = $name !== '' && str_contains($name, ' ')
            ? trim(substr($name, strpos($name, ' ')))
            : null;

        $properties = array_filter([
            'email' => $payment->customer_email,
            'firstname' => $first,
            'lastname' => $last,
            'lifecyclestage' => 'customer',
        ], fn ($v) => $v !== null && $v !== '');

        // Try to create; on 409 (already exists) fall back to update by email.
        $response = $this->client()->post('/crm/v3/objects/contacts', [
            'properties' => $properties,
        ]);

        if ($response->status() === 409) {
            $this->client()->patch('/crm/v3/objects/contacts/'.urlencode($payment->customer_email).'?idProperty=email', [
                'properties' => $properties,
            ]);

            // Re-read the id from the conflict response body.
            $existingId = data_get($response->json(), 'message');

            return $this->findContactIdByEmail($payment->customer_email);
        }

        return data_get($response->json(), 'id');
    }

    /**
     * Look up a contact id by email.
     *
     * @param  string  $email
     *
     * @return string|null
     */
    protected function findContactIdByEmail(string $email): ?string
    {
        $response = $this->client()->post('/crm/v3/objects/contacts/search', [
            'filterGroups' => [[
                'filters' => [[
                    'propertyName' => 'email',
                    'operator' => 'EQ',
                    'value' => $email,
                ]],
            ]],
            'properties' => ['email'],
            'limit' => 1,
        ]);

        return data_get($response->json(), 'results.0.id');
    }

    /**
     * Open a deal for the purchase.
     *
     * @param  Payment  $payment
     *
     * @return string|null  the deal id
     */
    protected function createDeal(Payment $payment): ?string
    {
        $amount = $payment->amount !== null ? number_format($payment->amount / 100, 2, '.', '') : null;

        $properties = array_filter([
            'dealname' => ($payment->description ?: 'Maritime GEO order').' — '.$payment->customer_email,
            'amount' => $amount,
            'dealstage' => 'closedwon',
            'pipeline' => 'default',
        ], fn ($v) => $v !== null && $v !== '');

        $response = $this->client()->post('/crm/v3/objects/deals', [
            'properties' => $properties,
        ]);

        return data_get($response->json(), 'id');
    }

    /**
     * Associate a deal with a contact using the default association type.
     *
     * @param  string  $dealId
     * @param  string  $contactId
     *
     * @return void
     */
    protected function associateDealToContact(string $dealId, string $contactId): void
    {
        $this->client()->put(
            "/crm/v4/objects/deals/{$dealId}/associations/default/contacts/{$contactId}",
            []
        );
    }

    /**
     * A pre-authenticated, JSON HTTP client for the HubSpot API.
     */
    protected function client()
    {
        return Http::withToken($this->token)
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson();
    }
}
