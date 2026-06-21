<?php

declare(strict_types=1);

namespace App\Funnel\Attribution;

/**
 * Translates inbound webhook payloads into attribution rows. Framework-free
 * (operates on plain arrays) so it is fully unit-testable and reusable from
 * both the Laravel controllers and the CLI.
 */
final class AttributionRecorder
{
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
        $utm = $this->utm($payload);

        $row = new Attribution(
            id: $this->id('lead'),
            postId: $this->str($payload, ['post_id', 'postId', 'content_id']),
            platform: $this->str($payload, ['platform', 'source_platform']),
            utmSource: $utm['source'] !== '' ? $utm['source'] : 'direct',
            utmMedium: $utm['medium'],
            utmCampaign: $utm['campaign'],
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

    /**
     * @param array<string,mixed> $payload
     * @return array{source:string,medium:string,campaign:string}
     */
    private function utm(array $payload): array
    {
        return [
            'source' => $this->str($payload, ['utm_source', 'utmSource']),
            'medium' => $this->str($payload, ['utm_medium', 'utmMedium']),
            'campaign' => $this->str($payload, ['utm_campaign', 'utmCampaign']),
        ];
    }

    /** @param array<string,mixed> $payload */
    private function leadId(array $payload): ?string
    {
        $id = $this->str($payload, ['lead_id', 'leadId', 'contact_id', 'contactId', 'customer_id', 'customerId']);

        return $id !== '' ? $id : null;
    }

    /** @param array<string,mixed> $payload */
    private function revenueCents(array $payload): int
    {
        if (isset($payload['revenue_cents'])) {
            return (int) $payload['revenue_cents'];
        }
        // QuickBooks reports dollar amounts; convert to integer cents.
        $amount = $payload['amount'] ?? $payload['total_amount'] ?? $payload['TotalAmt'] ?? 0;

        return (int) round(((float) $amount) * 100);
    }

    /**
     * @param array<string,mixed> $payload
     * @param string[]            $keys
     */
    private function str(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && $payload[$key] !== '') {
                return (string) $payload[$key];
            }
        }

        return '';
    }

    private function id(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(8));
    }
}
