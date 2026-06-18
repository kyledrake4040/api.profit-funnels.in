<?php

declare(strict_types=1);

namespace App\Funnel\Content;

use RuntimeException;

/**
 * Composes a single before/after image for Google Business Profile posts.
 *
 * It uses the company's REAL job photos (before + after) side by side with
 * labels and a brand bar. When a photo is missing it renders a clearly-marked
 * placeholder panel telling you to drop in the real shot — it never fabricates
 * fake "results", which would breach GBP policy and mislead customers.
 *
 * Output is 1080x1350 (4:5) — the format GBP/Instagram favour.
 */
final class BeforeAfterCard
{
    private const W = 1080;
    private const H = 1350;
    private const LABEL_H = 90;
    private const BRAND_H = 120;

    private const FONT_CANDIDATES = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/mnt/skills/examples/canvas-design/canvas-fonts/InstrumentSans-Bold.ttf',
    ];

    private readonly ?string $fontPath;

    public function __construct()
    {
        if (! \extension_loaded('gd')) {
            throw new RuntimeException('The GD extension is required to build before/after cards.');
        }
        $env = getenv('FUNNEL_FONT');
        $this->fontPath = ($env !== false && is_file($env))
            ? $env
            : (array_values(array_filter(self::FONT_CANDIDATES, 'is_file'))[0] ?? null);
    }

    /** Returns true if a real photo was supplied for that side. */
    public function render(?string $beforePath, ?string $afterPath, string $brand, string $outFile): void
    {
        $img = imagecreatetruecolor(self::W, self::H);
        imagefilledrectangle($img, 0, 0, self::W, self::H, imagecolorallocate($img, 12, 38, 51));

        $panelH = self::H - self::BRAND_H;
        $half = intdiv(self::W, 2);

        $this->panel($img, 0, 0, $half, $panelH, $beforePath, 'BEFORE');
        $this->panel($img, $half, 0, $half, $panelH, $afterPath, 'AFTER');

        // Brand bar.
        $bar = imagecolorallocate($img, 8, 26, 36);
        imagefilledrectangle($img, 0, $panelH, self::W, self::H, $bar);
        $this->text($img, $brand, 40, self::W / 2, $panelH + (self::BRAND_H / 2), imagecolorallocate($img, 245, 245, 245), center: true);

        imagepng($img, $outFile);
        imagedestroy($img);
    }

    /** @param \GdImage|resource $img */
    private function panel($img, int $x, int $y, int $w, int $h, ?string $photo, string $label): void
    {
        if ($photo !== null && is_file($photo)) {
            $this->drawCover($img, $photo, $x, $y, $w, $h);
        } else {
            // Clearly-marked placeholder — never a fake result.
            imagefilledrectangle($img, $x, $y, $x + $w, $y + $h, imagecolorallocate($img, 40, 44, 52));
            $grey = imagecolorallocate($img, 150, 155, 165);
            $this->text($img, 'Add your real', 30, $x + $w / 2, $y + $h / 2 - 30, $grey, center: true);
            $this->text($img, strtoupper($label) . ' photo', 30, $x + $w / 2, $y + $h / 2 + 30, $grey, center: true);
        }

        // Label chip.
        $chip = imagecolorallocatealpha($img, 0, 0, 0, 50);
        imagefilledrectangle($img, $x, $y, $x + $w, $y + self::LABEL_H, $chip);
        $this->text($img, $label, 44, $x + $w / 2, $y + self::LABEL_H / 2, imagecolorallocate($img, 80, 200, 255), center: true);
    }

    /** @param \GdImage|resource $img Cover-fit a photo into a panel. */
    private function drawCover($img, string $photo, int $x, int $y, int $w, int $h): void
    {
        $info = @getimagesize($photo);
        if ($info === false) {
            return;
        }
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($photo),
            IMAGETYPE_PNG => @imagecreatefrompng($photo),
            IMAGETYPE_WEBP => @imagecreatefromwebp($photo),
            default => false,
        };
        if ($src === false) {
            return;
        }

        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max($w / $sw, $h / $sh);
        $cw = (int) ($w / $scale);
        $ch = (int) ($h / $scale);
        $sx = (int) (($sw - $cw) / 2);
        $sy = (int) (($sh - $ch) / 2);

        imagecopyresampled($img, $src, $x, $y, $sx, $sy, $w, $h, $cw, $ch);
        imagedestroy($src);
    }

    /** @param \GdImage|resource $img */
    private function text($img, string $text, int $size, float $cx, float $cy, int $color, bool $center = false): void
    {
        if ($this->fontPath !== null) {
            $box = imagettfbbox($size, 0, $this->fontPath, $text);
            $tw = abs($box[2] - $box[0]);
            $th = abs($box[7] - $box[1]);
            $x = $center ? (int) ($cx - $tw / 2) : (int) $cx;
            imagettftext($img, $size, 0, $x, (int) ($cy + $th / 2), $color, $this->fontPath, $text);

            return;
        }
        $x = $center ? (int) ($cx - (\strlen($text) * 5)) : (int) $cx;
        imagestring($img, 5, $x, (int) $cy, $text, $color);
    }
}
