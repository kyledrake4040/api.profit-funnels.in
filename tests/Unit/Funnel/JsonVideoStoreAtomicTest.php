<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel;

use App\Funnel\Storage\JsonVideoStore;
use App\Funnel\VideoPost;
use PHPUnit\Framework\TestCase;

final class JsonVideoStoreAtomicTest extends TestCase
{
    private string $dir;
    private string $path;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/funnel_atomic_' . uniqid();
        $this->path = $this->dir . '/queue.json';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    private function post(string $id, int $at): VideoPost
    {
        return new VideoPost($id, VideoPost::TYPE_VIRAL, 'T', 'h', 's', 'c', [], 'b', $at, ['tiktok']);
    }

    public function test_writes_leave_no_temp_files_behind(): void
    {
        $store = new JsonVideoStore($this->path);
        $store->save($this->post('a', 100));
        $store->save($this->post('b', 200));

        $leftovers = array_filter(
            glob($this->dir . '/*') ?: [],
            static fn (string $f): bool => str_contains($f, '.tmp')
        );

        self::assertSame([], array_values($leftovers), 'no .tmp files should remain after writes');
        self::assertFileExists($this->path);
    }

    public function test_persisted_file_is_always_valid_json(): void
    {
        $store = new JsonVideoStore($this->path);
        $store->save($this->post('a', 100));

        $decoded = json_decode((string) file_get_contents($this->path), true);

        self::assertIsArray($decoded);
        self::assertSame('a', $decoded[0]['id']);
    }

    public function test_cache_serves_reads_without_touching_disk_again(): void
    {
        $store = new JsonVideoStore($this->path);
        $store->save($this->post('a', 100));

        // Prime the cache.
        self::assertArrayHasKey('a', $store->all());

        // Corrupt the on-disk file directly; cached reads must still succeed.
        file_put_contents($this->path, 'not json at all');

        self::assertArrayHasKey('a', $store->all(), 'read should be served from cache');
    }

    public function test_cache_is_invalidated_on_write(): void
    {
        $store = new JsonVideoStore($this->path);
        $store->save($this->post('a', 100));
        self::assertCount(1, $store->all()); // primes cache

        $store->save($this->post('b', 200)); // must invalidate cache

        self::assertCount(2, $store->all());
        self::assertSame(['a', 'b'], array_keys($store->all()));
    }
}
