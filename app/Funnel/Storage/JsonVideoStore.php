<?php

declare(strict_types=1);

namespace App\Funnel\Storage;

use App\Funnel\VideoPost;

/**
 * Zero-infrastructure store: persists the content queue to a JSON file.
 *
 * Good enough for an MVP and fully runnable/testable anywhere. Swap for a
 * database-backed store later without changing callers.
 */
final class JsonVideoStore
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

        return $posts;
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
        file_put_contents(
            $this->path,
            json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
            LOCK_EX
        );
    }
}
