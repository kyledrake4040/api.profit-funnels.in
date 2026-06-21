<?php

declare(strict_types=1);

namespace App\Funnel\Content;

use App\Funnel\VideoPost;
use RuntimeException;

/**
 * The "machine that builds the videos".
 *
 * For each post it renders real 9:16 (1080x1920) scene-card frames with GD
 * (one card per line of the hook/script), writes a self-contained HTML
 * slideshow that plays the cards in the browser, and saves the script/caption/
 * hashtags/Canva brief alongside. If ffmpeg is installed it also stitches the
 * frames into an actual .mp4. With no ffmpeg you still get postable frames you
 * can drop straight into Canva or a slideshow.
 */
final class VideoBuilder
{
    private const W = 1080;
    private const H = 1920;
    private const SECONDS_PER_CARD = 3;

    /** Candidate fonts; first existing one wins. Override with FUNNEL_FONT. */
    private const FONT_CANDIDATES = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/mnt/skills/examples/canvas-design/canvas-fonts/InstrumentSans-Bold.ttf',
    ];

    private readonly ?string $fontPath;

    public function __construct(private readonly string $outputDir)
    {
        if (! \extension_loaded('gd')) {
            throw new RuntimeException('The GD extension is required to build video frames.');
        }
        $this->fontPath = $this->resolveFont();
    }

    /**
     * Build one post into $outputDir/<id>/ and return a manifest.
     *
     * @return array{dir:string,frames:string[],player:string,mp4:?string,cards:int}
     */
    public function build(VideoPost $post): array
    {
        $dir = rtrim($this->outputDir, '/') . '/' . $post->id;
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $cards = $this->cardsFor($post);
        $frames = [];
        foreach ($cards as $i => $card) {
            $file = sprintf('%s/frame_%02d.png', $dir, $i);
            $this->renderCard($card, $i, \count($cards), $post->type, $file);
            $frames[] = $file;
        }

        $player = $dir . '/player.html';
        file_put_contents($player, $this->playerHtml($post, $frames));

        file_put_contents(
            $dir . '/post.json',
            json_encode($post->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );

        $mp4 = $this->maybeRenderMp4($dir, $frames);

        return [
            'dir' => $dir,
            'frames' => $frames,
            'player' => $player,
            'mp4' => $mp4,
            'cards' => \count($cards),
        ];
    }

    /** @return string[] one card of text per scene */
    private function cardsFor(VideoPost $post): array
    {
        $cards = [$post->hook];
        foreach (explode("\n", $post->script) as $line) {
            $line = trim($line);
            if ($line !== '' && $line !== $post->hook) {
                $cards[] = $line;
            }
        }
        $cards[] = $post->caption;

        return $cards;
    }

    private function renderCard(string $text, int $index, int $total, string $type, string $file): void
    {
        $img = imagecreatetruecolor(self::W, self::H);

        // Background: warm for business tips, bold for viral.
        [$r, $g, $b] = $type === VideoPost::TYPE_VIRAL ? [17, 17, 27] : [12, 38, 51];
        imagefilledrectangle($img, 0, 0, self::W, self::H, imagecolorallocate($img, $r, $g, $b));

        $accent = imagecolorallocate($img, 80, 200, 255);
        $white = imagecolorallocate($img, 245, 245, 245);

        // Top progress dots so it reads like a multi-scene video.
        for ($i = 0; $i < $total; $i++) {
            $c = $i <= $index ? $accent : imagecolorallocate($img, 70, 70, 80);
            $x = 80 + $i * 60;
            imagefilledellipse($img, $x, 90, 26, 26, $c);
        }

        $this->drawWrapped($img, $text, $white, $accent);

        imagepng($img, $file);
        imagedestroy($img);
    }

    /** @param \GdImage|resource $img */
    private function drawWrapped($img, string $text, int $white, int $accent): void
    {
        $marginX = 90;
        $maxWidth = self::W - (2 * $marginX);

        if ($this->fontPath !== null) {
            $size = 58;
            $lines = $this->wrapTtf($text, $size, $maxWidth);
            $lineH = (int) round($size * 1.5);
            $blockH = \count($lines) * $lineH;
            $y = (int) ((self::H - $blockH) / 2) + $size;
            foreach ($lines as $line) {
                imagettftext($img, $size, 0, $marginX, $y, $white, $this->fontPath, $line);
                $y += $lineH;
            }
            // Accent underline bar near the bottom.
            imagefilledrectangle($img, $marginX, self::H - 170, $marginX + 160, self::H - 158, $accent);

            return;
        }

        // Fallback: GD bitmap font (no TTF available).
        $lines = $this->wrapChars($text, 38);
        $lineH = 40;
        $y = (int) ((self::H - (\count($lines) * $lineH)) / 2);
        foreach ($lines as $line) {
            imagestring($img, 5, $marginX, $y, $line, $white);
            $y += $lineH;
        }
    }

    /** @return string[] */
    private function wrapTtf(string $text, int $size, int $maxWidth): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $try = $current === '' ? $word : $current . ' ' . $word;
            $box = imagettfbbox($size, 0, (string) $this->fontPath, $try);
            $width = abs($box[2] - $box[0]);
            if ($width > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $try;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /** @return string[] */
    private function wrapChars(string $text, int $perLine): array
    {
        return explode("\n", wordwrap($text, $perLine, "\n", true));
    }

    /** @param string[] $frames @return ?string path to mp4 if ffmpeg available */
    private function maybeRenderMp4(string $dir, array $frames): ?string
    {
        $ffmpeg = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($ffmpeg === '' || $frames === []) {
            return null;
        }

        $mp4 = $dir . '/video.mp4';
        $cmd = sprintf(
            '%s -y -framerate 1/%d -i %s/frame_%%02d.png -c:v libx264 -r 30 -pix_fmt yuv420p -vf "scale=%d:%d" %s 2>/dev/null',
            escapeshellarg($ffmpeg),
            self::SECONDS_PER_CARD,
            escapeshellarg($dir),
            self::W,
            self::H,
            escapeshellarg($mp4)
        );
        shell_exec($cmd);

        return is_file($mp4) ? $mp4 : null;
    }

    /** @param string[] $frames */
    private function playerHtml(VideoPost $post, array $frames): string
    {
        $rel = array_map(static fn (string $f): string => basename($f), $frames);
        $json = json_encode($rel, JSON_UNESCAPED_SLASHES);
        $ms = self::SECONDS_PER_CARD * 1000;
        $title = htmlspecialchars($post->title, ENT_QUOTES);
        $caption = htmlspecialchars($post->caption, ENT_QUOTES);
        $tags = htmlspecialchars(implode(' ', $post->hashtags), ENT_QUOTES);

        return <<<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title}</title>
<style>
  body{margin:0;background:#111;color:#eee;font-family:system-ui,sans-serif;display:flex;flex-direction:column;align-items:center;gap:12px;padding:16px}
  .phone{width:270px;height:480px;border-radius:24px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.6);background:#000}
  .phone img{width:100%;height:100%;object-fit:cover;display:none}
  .phone img.on{display:block}
  .meta{max-width:320px;font-size:13px;line-height:1.5}
  .tags{color:#7cf}
  button{background:#2a7;border:0;color:#fff;padding:8px 16px;border-radius:8px;font-size:14px;cursor:pointer}
</style></head>
<body>
  <div class="phone" id="p"></div>
  <button onclick="play()">▶ Replay</button>
  <div class="meta"><b>{$title}</b><br>{$caption}<br><span class="tags">{$tags}</span></div>
<script>
  const frames={$json}, ms={$ms}, p=document.getElementById('p');
  frames.forEach((f,i)=>{const im=new Image();im.src=f;im.id='f'+i;if(i===0)im.className='on';p.appendChild(im);});
  let t;
  function play(){let i=0;clearInterval(t);show(0);t=setInterval(()=>{i++;if(i>=frames.length){clearInterval(t);return;}show(i);},ms);}
  function show(i){frames.forEach((_,j)=>document.getElementById('f'+j).className=j===i?'on':'');}
  play();
</script>
</body></html>
HTML;
    }

    private function resolveFont(): ?string
    {
        $env = getenv('FUNNEL_FONT');
        if ($env !== false && is_file($env)) {
            return $env;
        }
        foreach (self::FONT_CANDIDATES as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
