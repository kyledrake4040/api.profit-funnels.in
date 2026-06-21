<?php

declare(strict_types=1);

namespace App\Funnel\Publishing;

use App\Funnel\VideoPost;

interface PlatformPublisher
{
    /** Platform key, e.g. "tiktok", "instagram", "youtube". */
    public function name(): string;

    /** True when real credentials are present and the driver can post for real. */
    public function isConnected(): bool;

    public function publish(VideoPost $post): PublishResult;
}
