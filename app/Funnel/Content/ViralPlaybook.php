<?php

declare(strict_types=1);

namespace App\Funnel\Content;

/**
 * Encodes researched, current best practices for viral short-form video so
 * every generated post is built to convert attention into watch time.
 *
 * Sources (2025–2026): OpusClip hook formulas, Socialinsider retention data,
 * vidIQ / Buffer short-form studies. Key findings baked in here:
 *  - You have ~1.3–3s to stop the scroll; the hook must land immediately.
 *  - A hook should be < 12 words and pair spoken words with a visual interrupt.
 *  - ~80% watch muted → burned-in captions are mandatory (the VideoBuilder
 *    already burns text onto every frame).
 *  - 15–30s clips retain best (often 80%+); cut every 3–5s, micro-cuts early.
 *  - Tease early, pay off late; never open with a slow build or "hey guys".
 */
final class ViralPlaybook
{
    public const TARGET_MIN_SECONDS = 15;
    public const TARGET_MAX_SECONDS = 30;
    public const MAX_HOOK_WORDS = 12;

    /**
     * Hook formulas that each trigger a known response: curiosity, pattern
     * interrupt, self-relevance, or emotional arousal. "{t}" = topic/service.
     *
     * @var array<int,array{type:string,template:string}>
     */
    private const HOOK_FORMULAS = [
        ['type' => 'contrarian',        'template' => 'Everything you know about {t} is wrong'],
        ['type' => 'contradiction',     'template' => 'Stop doing {t} like this — here’s why'],
        ['type' => 'question/struggle', 'template' => 'Still fighting {t}? Watch this first'],
        ['type' => 'curiosity gap',     'template' => 'This {t} trick feels illegal to know'],
        ['type' => 'insider',           'template' => 'What pros won’t tell you about {t}'],
        ['type' => 'promise',           'template' => 'Fix {t} in 15 seconds — no tools'],
    ];

    /** Retention tactics added to every Canva brief. @var string[] */
    private const RETENTION_RULES = [
        'Hook in the first 1.3s: bold on-screen text + a visual pattern interrupt.',
        'Keep the hook under ' . self::MAX_HOOK_WORDS . ' words; no "hey guys", no slow build.',
        'Burn in big captions — ~80% of viewers watch muted.',
        'Target ' . self::TARGET_MIN_SECONDS . '–' . self::TARGET_MAX_SECONDS . 's; cut every 3–5s, micro-cuts in the first 2–3 shots.',
        'Tease the payoff early, reveal it late so they watch to the end.',
        'Use a trending audio; end on a loop or a "follow for more".',
    ];

    /** Build a scroll-stopping hook for a topic, rotating through formulas. */
    public function hookFor(string $topic, int $index): string
    {
        $formula = self::HOOK_FORMULAS[$index % \count(self::HOOK_FORMULAS)];

        return str_replace('{t}', $topic, $formula['template']);
    }

    public function formulaType(int $index): string
    {
        return self::HOOK_FORMULAS[$index % \count(self::HOOK_FORMULAS)]['type'];
    }

    /** Quick check used in tests/QA: is the hook tight enough to land in 3s? */
    public static function hookIsTight(string $hook): bool
    {
        return str_word_count($hook) <= self::MAX_HOOK_WORDS;
    }

    /** Multi-line retention checklist for the Canva brief. */
    public function retentionBrief(): string
    {
        $lines = array_map(static fn (string $r): string => '  • ' . $r, self::RETENTION_RULES);

        return "RETENTION CHECKLIST (research-backed):\n" . implode("\n", $lines);
    }
}
