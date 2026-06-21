<?php

declare(strict_types=1);

namespace App\Funnel\Publishing;

final class PublishResult
{
    public function __construct(
        public readonly string $platform,
        public readonly bool $success,
        public readonly string $message,
        public readonly ?string $reference = null,
    ) {
    }

    public static function ok(string $platform, string $reference, string $message = 'published'): self
    {
        return new self($platform, true, $message, $reference);
    }

    public static function fail(string $platform, string $message): self
    {
        return new self($platform, false, $message);
    }
}
