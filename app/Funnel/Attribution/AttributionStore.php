<?php

declare(strict_types=1);

namespace App\Funnel\Attribution;

/**
 * Persistence boundary for attribution rows. Two implementations exist:
 *  - {@see JsonAttributionStore}: zero-infra file store for the CLI / demos.
 *  - {@see EloquentAttributionStore}: database-backed, concurrency-safe store
 *    for production webhooks (atomic INSERT/UPDATE, no read-modify-write race).
 */
interface AttributionStore
{
    public function record(Attribution $row): void;

    /** @return array<string,Attribution> keyed by id */
    public function all(): array;

    /** @return Attribution[] */
    public function allForLead(string $leadId): array;

    /**
     * Set revenue on every row tied to a lead. Returns the number of rows
     * updated (0 when the lead is unknown).
     */
    public function setRevenueForLead(string $leadId, int $revenueCents): int;

    /**
     * Rows created at or after $since (unix seconds). Lets the report query a
     * window instead of loading everything.
     *
     * @return Attribution[]
     */
    public function recordedSince(int $since): array;
}
