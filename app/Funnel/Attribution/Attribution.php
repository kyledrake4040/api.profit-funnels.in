<?php

declare(strict_types=1);

namespace App\Funnel\Attribution;

/**
 * One attribution record: a lead that arrived from the funnel (or elsewhere),
 * optionally linked to the revenue it later produced.
 *
 * Mirrors the funnel_attribution database table (see the migration) but is a
 * plain value object so the engine and the `bin/funnel report` command can use
 * it without booting the framework.
 */
final class Attribution
{
    public function __construct(
        public readonly string $id,
        public readonly string $postId,
        public readonly string $platform,
        public readonly string $utmSource,
        public readonly string $utmMedium,
        public readonly string $utmCampaign,
        public readonly ?string $leadId,
        public readonly ?int $revenueCents,
        public readonly int $createdAt,
    ) {
    }

    public function isFunnel(): bool
    {
        return strtolower($this->utmSource) === 'funnel';
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->postId,
            'platform' => $this->platform,
            'utm_source' => $this->utmSource,
            'utm_medium' => $this->utmMedium,
            'utm_campaign' => $this->utmCampaign,
            'lead_id' => $this->leadId,
            'revenue_cents' => $this->revenueCents,
            'created_at' => $this->createdAt,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            postId: (string) ($data['post_id'] ?? ''),
            platform: (string) ($data['platform'] ?? ''),
            utmSource: (string) ($data['utm_source'] ?? ''),
            utmMedium: (string) ($data['utm_medium'] ?? ''),
            utmCampaign: (string) ($data['utm_campaign'] ?? ''),
            leadId: isset($data['lead_id']) && $data['lead_id'] !== '' ? (string) $data['lead_id'] : null,
            revenueCents: isset($data['revenue_cents']) && $data['revenue_cents'] !== null
                ? (int) $data['revenue_cents']
                : null,
            createdAt: (int) ($data['created_at'] ?? 0),
        );
    }

    public function withRevenue(int $revenueCents): self
    {
        return new self(
            $this->id,
            $this->postId,
            $this->platform,
            $this->utmSource,
            $this->utmMedium,
            $this->utmCampaign,
            $this->leadId,
            $revenueCents,
            $this->createdAt,
        );
    }
}
