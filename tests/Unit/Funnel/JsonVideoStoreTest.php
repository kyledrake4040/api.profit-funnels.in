<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel;

use App\Funnel\Storage\JsonVideoStore;
use App\Funnel\VideoPost;
use PHPUnit\Framework\TestCase;

final class JsonVideoStoreTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/funnel_test_' . uniqid() . '/queue.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
            @rmdir(\dirname($this->path));
        }
    }

    private function post(string $id, int $at, string $status = VideoPost::STATUS_PENDING): VideoPost
    {
        return new VideoPost($id, VideoPost::TYPE_BUSINESS, 'T', 'hook', 'a\nb', 'cap', ['#x'], 'brief', $at, ['tiktok'], $status);
    }

    public function test_save_and_find_roundtrip(): void
    {
        $store = new JsonVideoStore($this->path);
        $store->save($this->post('a', 100));

        $found = $store->find('a');
        self::assertNotNull($found);
        self::assertSame('a', $found->id);
        self::assertSame(100, $found->scheduledAt);
    }

    public function test_all_is_ordered_by_scheduled_time(): void
    {
        $store = new JsonVideoStore($this->path);
        $store->save($this->post('late', 300));
        $store->save($this->post('early', 100));
        $store->save($this->post('mid', 200));

        self::assertSame(['early', 'mid', 'late'], array_keys($store->all()));
    }

    public function test_due_returns_only_pending_posts_at_or_before_now(): void
    {
        $store = new JsonVideoStore($this->path);
        $store->save($this->post('past', 100));
        $store->save($this->post('future', 999));
        $store->save($this->post('done', 50, VideoPost::STATUS_PUBLISHED));

        $due = $store->due(500);
        self::assertCount(1, $due);
        self::assertSame('past', $due[0]->id);
    }
}
