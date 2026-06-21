<?php

declare(strict_types=1);

namespace App\Funnel\Attribution;

/**
 * Zero-infrastructure attribution store: persists rows to a JSON file with
 * atomic writes, so both the webhook controllers and `bin/funnel report` can
 * read/write attribution without a database. For production webhooks prefer
 * {@see EloquentAttributionStore}, which is concurrency-safe.
 */
final class JsonAttributionStore implements AttributionStore
{
    public function __construct(private readonly string $path)
    {
        $dir = \dirname($this->path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (! file_exists($this->path)) {
            $this->writeAll([]);
        }
    }

    public function record(Attribution $row): void
    {
        $all = $this->all();
        $all[$row->id] = $row;
        $this->writeAll($all);
    }

    /** @return array<string,Attribution> keyed by id */
    public function all(): array
    {
        $raw = file_get_contents($this->path);
        $decoded = $raw === false || $raw === '' ? [] : json_decode($raw, true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        $rows = [];
        foreach ($decoded as $data) {
            $row = Attribution::fromArray($data);
            $rows[$row->id] = $row;
        }

        return $rows;
    }

    /** @return Attribution[] */
    public function allForLead(string $leadId): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (Attribution $a): bool => $a->leadId === $leadId
        ));
    }

    /** @return Attribution[] */
    public function recordedSince(int $since): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (Attribution $a): bool => $a->createdAt >= $since
        ));
    }

    /**
     * Record revenue against every row tied to a lead. Returns the number of
     * rows updated (0 when the lead is unknown).
     */
    public function setRevenueForLead(string $leadId, int $revenueCents): int
    {
        $all = $this->all();
        $updated = 0;
        foreach ($all as $id => $row) {
            if ($row->leadId === $leadId) {
                $all[$id] = $row->withRevenue($revenueCents);
                $updated++;
            }
        }
        if ($updated > 0) {
            $this->writeAll($all);
        }

        return $updated;
    }

    /** @param array<string,Attribution> $rows */
    private function writeAll(array $rows): void
    {
        $data = array_map(static fn (Attribution $a): array => $a->toArray(), array_values($rows));
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        // Atomic: write to a unique temp file, then rename() over the target.
        $tmp = $this->path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            @unlink($tmp);
            throw new \RuntimeException("Failed to write attribution temp file: {$tmp}");
        }
        if (! rename($tmp, $this->path)) {
            @unlink($tmp);
            throw new \RuntimeException("Failed to commit attribution file: {$this->path}");
        }
    }
}
