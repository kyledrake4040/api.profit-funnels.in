<?php

declare(strict_types=1);

namespace App\Funnel\Publishing;

use App\Funnel\VideoPost;

/**
 * Always-available publisher that simulates a post (no network, no account).
 *
 * Use it to prove the pipeline end-to-end today. Swap in a real driver
 * (TikTok/Instagram/YouTube) once credentials are configured.
 */
final class DryRunPublisher implements PlatformPublisher
{
    public function __construct(private readonly string $platform)
    {
    }

    public function name(): string
    {
        return $this->platform;
    }

    public function isConnected(): bool
    {
        return false;
    }

    public function publish(VideoPost $post): PublishResult
    {
        return PublishResult::ok(
            $this->platform,
            'dryrun://' . $this->platform . '/' . $post->id,
            'DRY RUN — would post to ' . $this->platform
        );
    }
}
