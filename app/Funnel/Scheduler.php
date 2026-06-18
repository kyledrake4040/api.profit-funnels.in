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
 */
final class Scheduler
{
    /** @param PlatformPublisher[] $publishers */
    public function __construct(
        private readonly JsonVideoStore $store,
        private readonly array $publishers,
    ) {
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
                if (! \in_array($publisher->name(), $post->platforms, true)) {
                    continue;
                }
                $result = $publisher->publish($post);
                $results[$publisher->name()] = $result;
                $post->results[$publisher->name()] = $result->success
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
}
