<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel;

use App\Funnel\Content\BeforeAfterCard;
use PHPUnit\Framework\TestCase;

final class BeforeAfterCardTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        if (! \extension_loaded('gd')) {
            self::markTestSkipped('GD extension not available');
        }
        $this->dir = sys_get_temp_dir() . '/funnel_ba_' . uniqid();
        mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->dir);
    }

    public function test_it_renders_a_4x5_card_with_placeholders_when_no_photos(): void
    {
        $out = $this->dir . '/card.png';
        (new BeforeAfterCard())->render(null, null, 'Gulf Coast Painting PEI', $out);

        self::assertFileExists($out);
        $size = getimagesize($out);
        self::assertSame(1080, $size[0]);
        self::assertSame(1350, $size[1]);
    }

    public function test_it_uses_a_supplied_real_photo(): void
    {
        // Make a tiny real JPEG to act as a "before" photo.
        $photo = $this->dir . '/before.jpg';
        $im = imagecreatetruecolor(200, 200);
        imagefilledrectangle($im, 0, 0, 200, 200, imagecolorallocate($im, 10, 120, 30));
        imagejpeg($im, $photo);
        imagedestroy($im);

        $out = $this->dir . '/card2.png';
        (new BeforeAfterCard())->render($photo, null, 'Brand', $out);

        self::assertFileExists($out);
        $size = getimagesize($out);
        self::assertSame(1080, $size[0]);
    }
}
