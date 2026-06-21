<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel;

use App\Funnel\Publishing\PlatformPublisher;
use App\Funnel\Publishing\PublishResult;
use App\Funnel\Scheduler;
use App\Funnel\Storage\JsonVideoStore;
use App\Funnel\VideoPost;
use PHPUnit\Framework\TestCase;

final class SchedulerTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/funnel_sched_' . uniqid() . '/queue.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
            @rmdir(\dirname($this->path));
        }
    }

    private function post(string $id, int $at, array $platforms): VideoPost
    {
        return new VideoPost($id, VideoPost::TYPE_VIRAL, 'T', 'h', 's', 'c', [], 'b', $at, $platforms);
    }

    private function publisher(string $name, bool $ok): PlatformPublisher
    {
        return new class($name, $ok) implements PlatformPublisher {
            public function __construct(private string $name, private bool $ok)
            {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function isConnected(): bool
            {
                return true;
            }

            public function publish(VideoPost $post): PublishResult
            {
                return $this->ok
                    ? PublishResult::ok($this->name, 'ref-' . $post->id)
                    : PublishResult::fail($this->name, 'boom');
            }
        };
    }

    public function test_it_publishes_due_posts_and_marks_them_published(): void
    {
        $store = new JsonVideoStore($this->path);
        $store->save($this->post('a', 100, ['tiktok']));

        $report = (new Scheduler($store, [$this->publisher('tiktok', true)]))->run(500);

        self::assertArrayHasKey('a', $report);
        self::assertTrue($report['a']['tiktok']->success);
        self::assertSame(VideoPost::STATUS_PUBLISHED, $store->find('a')->status);
    }

    public function test_a_failing_platform_marks_the_post_failed(): void
    {
        $store = new JsonVideoStore($this->path);
        $store->save($this->post('a', 100, ['tiktok', 'instagram']));

        // No-op sleeper so the retry backoff doesn't slow the test down.
        $report = (new Scheduler($store, [
            $this->publisher('tiktok', true),
            $this->publisher('instagram', false),
        ], 3, static fn (int $s): null => null))->run(500);

        self::assertTrue($report['a']['tiktok']->success);
        self::assertFalse($report['a']['instagram']->success);
        self::assertSame(VideoPost::STATUS_FAILED, $store->find('a')->status);
    }

    public function test_it_skips_publishers_not_targeted_by_the_post(): void
    {
        $store = new JsonVideoStore($this->path);
        $store->save($this->post('a', 100, ['tiktok']));

        $report = (new Scheduler($store, [
            $this->publisher('tiktok', true),
            $this->publisher('youtube', false),
        ]))->run(500);

        self::assertArrayHasKey('tiktok', $report['a']);
        self::assertArrayNotHasKey('youtube', $report['a']);
        self::assertSame(VideoPost::STATUS_PUBLISHED, $store->find('a')->status);
    }

    public function test_future_posts_are_not_published(): void
    {
        $store = new JsonVideoStore($this->path);
        $store->save($this->post('future', 9999, ['tiktok']));

        $report = (new Scheduler($store, [$this->publisher('tiktok', true)]))->run(500);

        self::assertSame([], $report);
        self::assertSame(VideoPost::STATUS_PENDING, $store->find('future')->status);
    }
}
