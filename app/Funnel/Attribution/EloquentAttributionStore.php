<?php

declare(strict_types=1);

namespace App\Funnel\Attribution;

use App\Models\FunnelAttribution;
use Illuminate\Support\Carbon;

/**
 * Database-backed attribution store. Unlike the JSON store, writes are atomic
 * single-row INSERT/UPDATE statements, so concurrent webhooks cannot lose
 * updates, and the report queries an indexed time window instead of scanning
 * the whole dataset.
 */
final class EloquentAttributionStore implements AttributionStore
{
    public function record(Attribution $row): void
    {
        FunnelAttribution::create([
            'post_id' => $row->postId,
            'platform' => $row->platform,
            'utm_source' => $row->utmSource,
            'utm_medium' => $row->utmMedium,
            'utm_campaign' => $row->utmCampaign,
            'lead_id' => $row->leadId,
            'revenue_cents' => $row->revenueCents,
            'created_at' => Carbon::createFromTimestamp($row->createdAt),
        ]);
    }

    /** @return array<string,Attribution> */
    public function all(): array
    {
        $rows = [];
        foreach (FunnelAttribution::query()->orderBy('id')->get() as $model) {
            $row = $this->toAttribution($model);
            $rows[$row->id] = $row;
        }

        return $rows;
    }

    /** @return Attribution[] */
    public function allForLead(string $leadId): array
    {
        return FunnelAttribution::query()
            ->where('lead_id', $leadId)
            ->get()
            ->map(fn (FunnelAttribution $m): Attribution => $this->toAttribution($m))
            ->all();
    }

    public function setRevenueForLead(string $leadId, int $revenueCents): int
    {
        return FunnelAttribution::query()
            ->where('lead_id', $leadId)
            ->update(['revenue_cents' => $revenueCents]);
    }

    /** @return Attribution[] */
    public function recordedSince(int $since): array
    {
        return FunnelAttribution::query()
            ->where('created_at', '>=', Carbon::createFromTimestamp($since))
            ->get()
            ->map(fn (FunnelAttribution $m): Attribution => $this->toAttribution($m))
            ->all();
    }

    private function toAttribution(FunnelAttribution $m): Attribution
    {
        return new Attribution(
            id: (string) $m->id,
            postId: (string) $m->post_id,
            platform: (string) $m->platform,
            utmSource: (string) $m->utm_source,
            utmMedium: (string) $m->utm_medium,
            utmCampaign: (string) $m->utm_campaign,
            leadId: $m->lead_id !== null && $m->lead_id !== '' ? (string) $m->lead_id : null,
            revenueCents: $m->revenue_cents !== null ? (int) $m->revenue_cents : null,
            createdAt: $m->created_at !== null ? $m->created_at->getTimestamp() : 0,
        );
    }
}
