<?php

declare(strict_types=1);

namespace App\Funnel\Storage;

use App\Funnel\VideoPost;

/**
 * Zero-infrastructure store: persists the content queue to a JSON file.
 *
 * Good enough for an MVP and fully runnable/testable anywhere. Swap for a
 * database-backed store later without changing callers.
 *
 * Durability: writes go to a unique temp file in the same directory and are
 * atomically rename()d into place, so a crash mid-write can never leave a
 * truncated or half-written queue on disk. Reads are served from an in-memory
 * cache that is invalidated on every write.
 */
final class JsonVideoStore
{
    /** @var array<string,VideoPost>|null in-memory cache, null when stale */
    private ?array $cache = null;

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

    public function save(VideoPost $post): void
    {
        $all = $this->all();
        $all[$post->id] = $post;
        $this->writeAll($all);
    }

    public function find(string $id): ?VideoPost
    {
        return $this->all()[$id] ?? null;
    }

    /** @return array<string,VideoPost> keyed by id, ordered by scheduled time */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $raw = file_get_contents($this->path);
        $decoded = $raw === false || $raw === '' ? [] : json_decode($raw, true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        $posts = [];
        foreach ($decoded as $row) {
            $post = VideoPost::fromArray($row);
            $posts[$post->id] = $post;
        }

        uasort($posts, static fn (VideoPost $a, VideoPost $b): int => $a->scheduledAt <=> $b->scheduledAt);

        return $this->cache = $posts;
    }

    /** @return VideoPost[] posts that are pending and due at or before $now */
    public function due(int $now): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (VideoPost $p): bool => $p->isDue($now)
        ));
    }

    /** @param array<string,VideoPost> $posts */
    private function writeAll(array $posts): void
    {
        $rows = array_map(static fn (VideoPost $p): array => $p->toArray(), array_values($posts));
        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        // Write to a unique temp file in the same directory, then atomically
        // rename() it over the target. rename() within one filesystem is
        // atomic, so readers never observe a partial write.
        $tmp = $this->path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            @unlink($tmp);
            throw new \RuntimeException("Failed to write queue temp file: {$tmp}");
        }
        if (! rename($tmp, $this->path)) {
            @unlink($tmp);
            throw new \RuntimeException("Failed to commit queue file: {$this->path}");
        }

        // The on-disk copy just changed; drop the cache so the next read reloads.
        $this->cache = null;
    }
}
