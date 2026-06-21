<?php

declare(strict_types=1);

namespace App\Funnel;

use App\Funnel\Publishing\PlatformPublisher;
use App\Funnel\Publishing\PublishResult;
use App\Funnel\Storage\JsonVideoStore;

/**
 * Picks due posts off the queue and publishes each to every configured
 * platform, recording the result and updating the post status. Designed to be
 * run on a cron / Laravel scheduler (e.g. every 5 minutes).
 *
 * Resilience:
 *  - Each platform publish is retried up to {@see $maxAttempts} times with
 *    exponential backoff, so a transient error (rate limit, blip) recovers
 *    without operator action.
 *  - Failures are isolated per platform/post: one platform throwing or failing
 *    never aborts the rest of the queue.
 *  - Publishing is idempotent: a platform already recorded as successfully
 *    published for a post is skipped on re-run, so retries never double-post.
 */
final class Scheduler
{
    /** @var callable(int):void */
    private $sleeper;

    /**
     * @param PlatformPublisher[]     $publishers
     * @param callable(int):void|null $sleeper backoff hook (seconds); injectable for tests
     */
    public function __construct(
        private readonly JsonVideoStore $store,
        private readonly array $publishers,
        private readonly int $maxAttempts = 3,
        ?callable $sleeper = null,
    ) {
        $this->sleeper = $sleeper ?? static function (int $seconds): void {
            if ($seconds > 0) {
                sleep($seconds);
            }
        };
    }

    /**
     * @return array<string,array<string,PublishResult>> postId => platform => result
     */
    public function run(int $now): array
    {
        $report = [];

        foreach ($this->store->due($now) as $post) {
            $results = [];
            $allOk = true;

            foreach ($this->publishers as $publisher) {
                $platform = $publisher->name();
                if (! \in_array($platform, $post->platforms, true)) {
                    continue;
                }

                // Idempotency: don't re-post a platform that already succeeded.
                if ($this->alreadyPublished($post, $platform)) {
                    continue;
                }

                $result = $this->publishWithRetry($publisher, $post);
                $results[$platform] = $result;
                $post->results[$platform] = $result->success
                    ? ($result->reference ?? 'ok')
                    : 'FAILED: ' . $result->message;
                $allOk = $allOk && $result->success;
            }

            $post->status = $results === []
                ? VideoPost::STATUS_PENDING
                : ($allOk ? VideoPost::STATUS_PUBLISHED : VideoPost::STATUS_FAILED);

            $this->store->save($post);
            $report[$post->id] = $results;
        }

        return $report;
    }

    /**
     * Publish to a single platform, retrying transient failures with
     * exponential backoff (1s, 2s, 4s, ...). Any thrown error is caught and
     * treated as a failed attempt so it can be retried rather than aborting
     * the queue. Returns the result of the last attempt.
     */
    public function publishWithRetry(PlatformPublisher $publisher, VideoPost $post): PublishResult
    {
        $result = PublishResult::fail($publisher->name(), 'not attempted');

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                $result = $publisher->publish($post);
            } catch (\Throwable $e) {
                $result = PublishResult::fail($publisher->name(), 'exception: ' . $e->getMessage());
            }

            if ($result->success) {
                return $result;
            }

            if ($attempt < $this->maxAttempts) {
                ($this->sleeper)(2 ** ($attempt - 1));
            }
        }

        return $result;
    }

    private function alreadyPublished(VideoPost $post, string $platform): bool
    {
        $prior = $post->results[$platform] ?? null;

        return is_string($prior) && $prior !== '' && ! str_starts_with($prior, 'FAILED');
    }
}
