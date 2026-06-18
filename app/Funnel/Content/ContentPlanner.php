<?php

declare(strict_types=1);

namespace App\Funnel\Content;

use App\Funnel\FunnelConfig;
use App\Funnel\VideoPost;

/**
 * Builds an alternating content calendar:
 *   slot 0 -> business video  (VALUE-FIRST: solves a real customer problem)
 *   slot 1 -> viral-style video (trend formats, kept on-brand)
 *   slot 2 -> business ... and so on.
 *
 * Business posts teach/solve a problem instead of selling — the brand is only
 * a soft mention at the end. Each post carries a hook, script, caption,
 * hashtags, and a scene-by-scene brief the VideoBuilder turns into frames.
 *
 * It produces ORIGINAL briefs informed by common short-form formats; it does
 * not copy anyone else's video.
 */
final class ContentPlanner
{
    /**
     * Problem-solving topics (value-first, not sales). Each: hook + tip steps.
     *
     * @var array<int,array{title:string,hook:string,steps:string[]}>
     */
    private const PROBLEM_TOPICS = [
        [
            'title' => 'Green algae on your siding',
            'hook' => 'That green film on your siding isn’t dirt — here’s what it actually is',
            'steps' => [
                'It’s algae, and a pressure washer on full blast can force water behind the siding.',
                'Use a low-pressure rinse + a 30%% vinegar or oxygen-bleach mix, top to bottom.',
                'Let it dwell 10 minutes, then rinse — no scrubbing needed.',
                'Do it on a cloudy day so the solution doesn’t dry too fast.',
            ],
        ],
        [
            'title' => 'Why deck stain peels',
            'hook' => 'If your deck stain keeps peeling, you skipped this one step',
            'steps' => [
                'Peeling almost always means the wood was sealed while still damp.',
                'After cleaning, wait 48 hours of dry weather before staining.',
                'Do the “sprinkle test” — water should soak in, not bead up.',
                'Thin coats beat one thick coat every time.',
            ],
        ],
        [
            'title' => 'Salt air vs. exterior paint',
            'hook' => 'Why coastal homes lose their paint twice as fast',
            'steps' => [
                'Salt air leaves a film that breaks the bond between paint and wall.',
                'Rinse exterior walls with fresh water 2–3 times a year.',
                'Choose a 100%% acrylic paint — it flexes with humidity swings.',
                'Repaint south/west walls first; they take the most weather.',
            ],
        ],
        [
            'title' => 'Pressure washing mistakes',
            'hook' => '3 pressure-washing mistakes that cost homeowners thousands',
            'steps' => [
                'Too-close + too-high PSI gouges wood and cracks vinyl.',
                'Spraying upward drives water under siding and into walls.',
                'Skipping the right nozzle — use a wide 40° tip on surfaces.',
                'Always work top-down and keep the wand moving.',
            ],
        ],
        [
            'title' => 'Wash or repaint?',
            'hook' => 'How to tell if your house needs paint — or just a wash',
            'steps' => [
                'Wipe the wall with a damp cloth — chalky residue means failing paint.',
                'Hairline cracks + flaking = repaint. Just grime = wash.',
                'Fading on one side only usually means a wash will revive it.',
                'When in doubt, wash first; it’s cheaper and you’ll see what’s left.',
            ],
        ],
        [
            'title' => 'Mildew on shady walls',
            'hook' => 'The black spots on your north wall aren’t mould — usually',
            'steps' => [
                'Shady, damp walls grow mildew that looks worse than it is.',
                'Treat with an oxygen-bleach solution, not chlorine on plants nearby.',
                'Trim back shrubs so the wall gets airflow and light.',
                'A mildew-resistant additive in repaint stops it coming back.',
            ],
        ],
    ];

    /** Proven short-form formats (structure, not stolen content). */
    private const VIRAL_FORMATS = [
        'Oddly satisfying before/after reveal',
        'Text-on-screen hook + fast transformation',
        'POV storytime over a satisfying clip',
        '“Things you didn’t know” listicle',
        'Day-in-the-life / behind the scenes',
    ];

    public function __construct(private readonly FunnelConfig $config)
    {
    }

    /**
     * @param int $count       number of posts to plan
     * @param int $startAt     unix timestamp of the first post
     * @param int $intervalSec spacing between posts (default 12h)
     *
     * @return VideoPost[]
     */
    public function plan(int $count, int $startAt, int $intervalSec = 43200): array
    {
        $posts = [];
        $services = $this->config->services !== [] ? $this->config->services : ['our services'];

        for ($i = 0; $i < $count; $i++) {
            $scheduledAt = $startAt + ($i * $intervalSec);
            $posts[] = ($i % 2) === 0
                ? $this->businessPost($i, $scheduledAt)
                : $this->viralPost($i, $services[$i % \count($services)], $scheduledAt);
        }

        return $posts;
    }

    private function businessPost(int $index, int $scheduledAt): VideoPost
    {
        $topic = self::PROBLEM_TOPICS[($index / 2) % \count(self::PROBLEM_TOPICS)];
        $biz = $this->config->businessName;
        $loc = $this->config->location;

        $hook = $topic['hook'];
        $script = $hook . "\n" . implode("\n", array_map(
            static fn (int $n, string $s): string => ($n + 1) . '. ' . $s,
            array_keys($topic['steps']),
            $topic['steps'],
        ));

        // Value-first caption: the tip is the point; the brand is a soft footer.
        $caption = $this->stripFormat($hook) . ' 👇 '
            . 'Save this for later. Honest home-care tips from ' . $biz . ' in ' . $loc . '.';

        $canvaBrief = implode("\n", [
            'CANVA BRIEF (value tip — ' . $topic['title'] . '):',
            '- Format: 9:16 vertical, ~15–25s, captions burned in.',
            '- Card 1: bold hook only — "' . $this->stripFormat($hook) . '"',
            '- One tip per card, big readable text, calm satisfying b-roll.',
            '- Last card: small logo + "' . $biz . '" (no hard sell).',
            '- Trending low-key audio; keep it helpful, not salesy.',
        ]);

        return new VideoPost(
            id: $this->id($index, $scheduledAt),
            type: VideoPost::TYPE_BUSINESS,
            title: $topic['title'],
            hook: $this->stripFormat($hook),
            script: $this->stripFormat($script),
            caption: $caption,
            hashtags: $this->hashtags($topic['title']),
            canvaBrief: $canvaBrief,
            scheduledAt: $scheduledAt,
            platforms: $this->config->platforms,
        );
    }

    private function viralPost(int $index, string $service, int $scheduledAt): VideoPost
    {
        $format = self::VIRAL_FORMATS[$index % \count(self::VIRAL_FORMATS)];
        $biz = $this->config->businessName;

        $hook = 'You won’t believe how satisfying this ' . $service . ' clip is 🤯';
        $script = implode("\n", [
            'Format: ' . $this->stripFormat($format) . '.',
            '1. First 1s = pattern interrupt + bold on-screen hook.',
            '2. 2–8s = the satisfying / relatable payoff.',
            '3. Last 2s = soft brand mention + "follow for more".',
        ]);
        $caption = $this->stripFormat($hook) . ' Follow ' . $biz . ' for more 🔥';

        $canvaBrief = implode("\n", [
            'CANVA BRIEF (viral-style — ' . $this->stripFormat($format) . '):',
            '- Format: 9:16 vertical, 7–12s, trending audio.',
            '- Strong on-screen hook in the first second.',
            '- Fast cuts every 1–2s; subtle brand watermark.',
            '- CTA sticker: "Follow for more".',
        ]);

        return new VideoPost(
            id: $this->id($index, $scheduledAt),
            type: VideoPost::TYPE_VIRAL,
            title: 'Viral-style: ' . $this->stripFormat($format),
            hook: $this->stripFormat($hook),
            script: $this->stripFormat($script),
            caption: $caption,
            hashtags: $this->hashtags($service, viral: true),
            canvaBrief: $canvaBrief,
            scheduledAt: $scheduledAt,
            platforms: $this->config->platforms,
        );
    }

    /** @return string[] */
    private function hashtags(string $topic, bool $viral = false): array
    {
        $slug = static fn (string $s): string => '#' . preg_replace('/[^a-z0-9]/', '', strtolower($s));
        $base = [
            '#pei',
            $slug($this->config->location),
            '#homecaretips',
            '#pressurewashing',
            '#painting',
        ];
        $viralTags = ['#fyp', '#satisfying', '#viral', '#foryou'];

        return array_values(array_unique(array_merge($base, $viral ? $viralTags : ['#diy', $slug($topic)])));
    }

    /** Remove printf-style placeholders used in tip percentages (e.g. "30%%"). */
    private function stripFormat(string $text): string
    {
        return str_replace('%%', '%', $text);
    }

    private function id(int $index, int $scheduledAt): string
    {
        return sprintf('post_%03d_%d', $index, $scheduledAt);
    }
}
