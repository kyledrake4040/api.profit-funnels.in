<?php

declare(strict_types=1);

namespace App\Funnel\Attribution;

/**
 * Translates inbound webhook payloads into attribution rows. Framework-free
 * (operates on plain arrays) so it is fully unit-testable and reusable from
 * both the Laravel controllers and the CLI.
 *
 * Payload shapes handled:
 *  - Flat keys: utm_source, contact_id, amount, ...
 *  - GoHighLevel: UTM nested under "attributionSource"/"lastAttributionSource",
 *    the contact under "contact", workflow values under "customData".
 *  - QuickBooks: "CustomerRef": {"value": "123"}, "TotalAmt"/"Balance".
 */
final class AttributionRecorder
{
    /** Nested objects we also look inside when resolving a value. */
    private const CONTAINERS = ['attributionSource', 'lastAttributionSource', 'customData', 'contact', 'customer', 'data'];

    /** @var callable():int */
    private $clock;

    /** @param callable():int|null $clock injectable for tests */
    public function __construct(
        private readonly JsonAttributionStore $store,
        ?callable $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * Record an inbound GoHighLevel lead. Every lead is stored so the report
     * can compare funnel vs. other; leads carrying utm_source=funnel are the
     * ones attributed to the funnel.
     *
     * @param array<string,mixed> $payload
     */
    public function recordLead(array $payload): Attribution
    {
        $source = $this->find($payload, ['utm_source', 'utmSource']);

        $row = new Attribution(
            id: $this->id('lead'),
            postId: $this->find($payload, ['post_id', 'postId', 'content_id']),
            platform: $this->find($payload, ['platform', 'source_platform', 'sessionSource']),
            utmSource: $source !== '' ? $source : 'direct',
            utmMedium: $this->find($payload, ['utm_medium', 'utmMedium']),
            utmCampaign: $this->find($payload, ['utm_campaign', 'utmCampaign']),
            leadId: $this->leadId($payload),
            revenueCents: null,
            createdAt: ($this->clock)(),
        );

        $this->store->record($row);

        return $row;
    }

    /**
     * Record that an invoice was paid for a lead, updating revenue on the
     * matching attribution rows. Returns the number of rows updated.
     *
     * @param array<string,mixed> $payload
     */
    public function recordPaidInvoice(array $payload): int
    {
        $leadId = $this->leadId($payload);
        if ($leadId === null) {
            return 0;
        }

        return $this->store->setRevenueForLead($leadId, $this->revenueCents($payload));
    }

    /** @param array<string,mixed> $payload */
    private function leadId(array $payload): ?string
    {
        $id = $this->find($payload, [
            'lead_id', 'leadId', 'contact_id', 'contactId', 'customer_id', 'customerId', 'CustomerRef',
        ]);

        // Fall back to the id nested inside a contact/customer object.
        if ($id === '') {
            foreach (['contact', 'customer'] as $container) {
                if (isset($payload[$container]) && is_array($payload[$container])) {
                    $id = $this->scalar($payload[$container], 'id');
                    if ($id !== '') {
                        break;
                    }
                }
            }
        }

        return $id !== '' ? $id : null;
    }

    /** @param array<string,mixed> $payload */
    private function revenueCents(array $payload): int
    {
        $cents = $this->find($payload, ['revenue_cents']);
        if ($cents !== '') {
            return (int) $cents;
        }

        // QuickBooks etc. report dollar amounts; convert to integer cents.
        $amount = $this->find($payload, ['amount', 'total_amount', 'TotalAmt', 'Balance', 'TotalAmount']);

        return (int) round(((float) $amount) * 100);
    }

    /**
     * Resolve the first non-empty value for any of $keys, looking at the top
     * level first and then inside the known nested containers.
     *
     * @param array<string,mixed> $payload
     * @param string[]            $keys
     */
    private function find(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $this->scalar($payload, $key);
            if ($value !== '') {
                return $value;
            }
        }

        foreach (self::CONTAINERS as $container) {
            if (! isset($payload[$container]) || ! is_array($payload[$container])) {
                continue;
            }
            foreach ($keys as $key) {
                $value = $this->scalar($payload[$container], $key);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * Read a scalar at $key, unwrapping a nested ref object such as
     * QuickBooks' {"value": "123"} into "123".
     *
     * @param array<string,mixed> $arr
     */
    private function scalar(array $arr, string $key): string
    {
        if (! array_key_exists($key, $arr)) {
            return '';
        }

        $value = $arr[$key];
        if (is_array($value)) {
            $value = $value['value'] ?? $value['id'] ?? '';
        }

        return $value === null || $value === '' ? '' : (string) $value;
    }

    private function id(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(8));
    }
}
