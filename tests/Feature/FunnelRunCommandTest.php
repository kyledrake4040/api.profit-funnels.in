<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Funnel\Storage\JsonVideoStore;
use App\Funnel\VideoPost;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FunnelRunCommandTest extends TestCase
{
    private string $queue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queue = sys_get_temp_dir() . '/funnel_run_' . uniqid() . '/queue.json';
        config(['funnel.queue_path' => $this->queue]);
    }

    protected function tearDown(): void
    {
        @unlink($this->queue);
        @rmdir(\dirname($this->queue));
        parent::tearDown();
    }

    public function test_it_is_dormant_when_disabled(): void
    {
        config(['funnel.enabled' => false]);

        $exit = Artisan::call('funnel:run');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('disabled', Artisan::output());
    }

    public function test_it_reports_when_nothing_is_due(): void
    {
        config(['funnel.enabled' => true]);

        $exit = Artisan::call('funnel:run');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No posts are due', Artisan::output());
    }

    public function test_it_publishes_a_due_post_in_dry_run(): void
    {
        config(['funnel.enabled' => true]);

        $store = new JsonVideoStore($this->queue);
        $store->save(new VideoPost(
            'p1',
            VideoPost::TYPE_VIRAL,
            'T',
            'hook',
            'script',
            'caption',
            ['#x'],
            'brief',
            100, // due (scheduled in the past)
            ['tiktok'],
        ));

        $exit = Artisan::call('funnel:run');
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('p1', $output);
        $this->assertStringContainsString('tiktok', $output);
        $this->assertStringContainsString('DRY RUN', $output);
    }
}
