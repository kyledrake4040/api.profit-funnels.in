<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel;

use App\Funnel\Content\VideoBuilder;
use App\Funnel\VideoPost;
use PHPUnit\Framework\TestCase;

final class VideoBuilderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        if (! \extension_loaded('gd')) {
            self::markTestSkipped('GD extension not available');
        }
        $this->dir = sys_get_temp_dir() . '/funnel_build_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            foreach (glob($this->dir . '/*/*') ?: [] as $f) {
                unlink($f);
            }
            foreach (glob($this->dir . '/*') ?: [] as $d) {
                @rmdir($d);
            }
            @rmdir($this->dir);
        }
    }

    private function post(): VideoPost
    {
        return new VideoPost(
            id: 'post_test',
            type: VideoPost::TYPE_BUSINESS,
            title: 'Green algae on your siding',
            hook: 'That green film is algae',
            script: "Step one do this\nStep two do that\nStep three finish",
            caption: 'Save this tip',
            hashtags: ['#pei', '#diy'],
            canvaBrief: 'brief',
            scheduledAt: 100,
            platforms: ['tiktok'],
        );
    }

    public function test_it_renders_one_frame_per_card_plus_player_and_metadata(): void
    {
        $manifest = (new VideoBuilder($this->dir))->build($this->post());

        // hook + 3 script lines + caption = 5 cards
        self::assertSame(5, $manifest['cards']);
        self::assertCount(5, $manifest['frames']);

        foreach ($manifest['frames'] as $frame) {
            self::assertFileExists($frame);
            $size = getimagesize($frame);
            self::assertSame(1080, $size[0]);
            self::assertSame(1920, $size[1]);
        }

        self::assertFileExists($manifest['player']);
        self::assertStringContainsString('Green algae', file_get_contents($manifest['player']));
        self::assertFileExists($manifest['dir'] . '/post.json');
    }
}
