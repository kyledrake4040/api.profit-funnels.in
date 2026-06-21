<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel;

use App\Funnel\Publishing\PlatformPublisher;
use App\Funnel\Publishing\PublishResult;
use App\Funnel\Scheduler;
use App\Funnel\Storage\JsonVideoStore;
use App\Funnel\VideoPost;
use PHPUnit\Framework\TestCase;

final class SchedulerRetryTest extends TestCase
{
    private string $path;

    /** @var int[] recorded backoff sleeps */
    private array $slept = [];

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/funnel_retry_' . uniqid() . '/queue.json';
        $this->slept = [];
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
            @rmdir(\dirname($this->path));
        }
    }

    private function post(string $id, array $platforms, array $results = []): VideoPost
    {
        return new VideoPost(
            $id,
            VideoPost::TYPE_VIRAL,
            'T',
            'h',
            's',
            'c',
            [],
            'b',
            100,
            $platforms,
            VideoPost::STATUS_PENDING,
            $results
        );
    }

    /** Publisher that fails $failTimes times, then succeeds. */
    private function flaky(string $name, int $failTimes): PlatformPublisher
    {
        return new class($name, $failTimes) implements PlatformPublisher {
            public int $calls = 0;

            public function __construct(private string $name, private int $failTimes)
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
                $this->calls++;

                return $this->calls > $this->failTimes
                    ? PublishResult::ok($this->name, 'ref-' . $this->calls)
                    : PublishResult::fail($this->name, 'transient');
            }
        };
    }

    private function throwing(string $name): PlatformPublisher
    {
        return new class($name) implements PlatformPublisher {
            public int $calls = 0;

            public function __construct(private string $name)
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
                $this->calls++;

                throw new \RuntimeException('network down');
            }
        };
    }

    private function scheduler(JsonVideoStore $store, array $publishers, int $maxAttempts = 3): Scheduler
    {
        return new Scheduler(
            $store,
            $publishers,
            $maxAttempts,
            function (int $seconds): void {
                $this->slept[] = $seconds;
            }
        );
    }

    public function test_it_retries_a_transient_failure_then_succeeds(): void
    {
        $store = new JsonVideoStore($this->path);
        $store->save($this->post('a', ['tiktok']));
        $publisher = $this->flaky('tiktok', 2); // fail, fail, ok

        $report = $this->scheduler($store, [$publisher])->run(500);

        self::assertTrue($report['a']['tiktok']->success);
        self::assertSame(3, $publisher->calls);
        self::assertSame(VideoPost::STATUS_PUBLISHED, $store->find('a')->status);
    }

    public function test_backoff_is_exponential_between_attempts(): void
    {
        $store = new JsonVideoStore($this->path);
        $store->save($this->post('a', ['tiktok']));

        $this->scheduler($store, [$this->flaky('tiktok', 5)], 3)->run(500);

        // Two sleeps between three attempts: 1s then 2s.
        self::assertSame([1, 2], $this->slept);
    }

    public function test_it_gives_up_after_max_attempts(): void
    {
        $store = new JsonVideoStore($this->path);
        $store->save($this->post('a', ['tiktok']));
        $publisher = $this->flaky('tiktok', 99);

        $report = $this->scheduler($store, [$publisher], 3)->run(500);

        self::assertFalse($report['a']['tiktok']->success);
        self::assertSame(3, $publisher->calls);
        self::assertSame(VideoPost::STATUS_FAILED, $store->find('a')->status);
    }

    public function test_a_thrown_error_is_caught_and_does_not_abort_the_queue(): void
    {
        $store = new JsonVideoStore($this->path);
        $store->save($this->post('a', ['tiktok', 'instagram']));

        $report = $this->scheduler($store, [
            $this->throwing('tiktok'),
            $this->flaky('instagram', 0), // succeeds immediately
        ])->run(500);

        self::assertFalse($report['a']['tiktok']->success);
        self::assertStringContainsString('network down', $report['a']['tiktok']->message);
        self::assertTrue($report['a']['instagram']->success);
    }

    public function test_already_published_platform_is_not_reposted(): void
    {
        $store = new JsonVideoStore($this->path);
        // tiktok already has a successful reference recorded.
        $store->save($this->post('a', ['tiktok', 'instagram'], ['tiktok' => 'ref-earlier']));
        $tiktok = $this->flaky('tiktok', 0);
        $instagram = $this->flaky('instagram', 0);

        $report = $this->scheduler($store, [$tiktok, $instagram])->run(500);

        self::assertSame(0, $tiktok->calls, 'already-published platform must be skipped');
        self::assertSame(1, $instagram->calls);
        self::assertArrayNotHasKey('tiktok', $report['a']);
        self::assertSame(VideoPost::STATUS_PUBLISHED, $store->find('a')->status);
    }
}
