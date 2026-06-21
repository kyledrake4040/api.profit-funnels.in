<?php

declare(strict_types=1);

namespace App\Funnel;

/**
 * A single piece of content scheduled to be published to social platforms.
 */
final class VideoPost
{
    public const TYPE_BUSINESS = 'business';
    public const TYPE_VIRAL = 'viral';
    public const TYPE_BEFORE_AFTER = 'before_after';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';

    /**
     * @param string[]              $platforms
     * @param string[]              $hashtags
     * @param array<string,string>  $results   platform => reference/message
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public string $hook,
        public string $script,
        public string $caption,
        public array $hashtags,
        public string $canvaBrief,
        public int $scheduledAt,
        public array $platforms,
        public string $status = self::STATUS_PENDING,
        public array $results = [],
    ) {
    }

    public function isDue(int $now): bool
    {
        return $this->status === self::STATUS_PENDING && $this->scheduledAt <= $now;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'hook' => $this->hook,
            'script' => $this->script,
            'caption' => $this->caption,
            'hashtags' => $this->hashtags,
            'canva_brief' => $this->canvaBrief,
            'scheduled_at' => $this->scheduledAt,
            'platforms' => $this->platforms,
            'status' => $this->status,
            'results' => $this->results,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            type: (string) $data['type'],
            title: (string) $data['title'],
            hook: (string) ($data['hook'] ?? ''),
            script: (string) ($data['script'] ?? ''),
            caption: (string) $data['caption'],
            hashtags: array_values((array) ($data['hashtags'] ?? [])),
            canvaBrief: (string) ($data['canva_brief'] ?? ''),
            scheduledAt: (int) $data['scheduled_at'],
            platforms: array_values((array) ($data['platforms'] ?? [])),
            status: (string) ($data['status'] ?? self::STATUS_PENDING),
            results: (array) ($data['results'] ?? []),
        );
    }
}
