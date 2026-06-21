<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel;

use App\Funnel\Content\ContentPlanner;
use App\Funnel\FunnelConfig;
use PHPUnit\Framework\TestCase;

/**
 * The planner enforces a hard ceiling on posting cadence so a misconfigured
 * high value can never queue spam-level volume.
 */
final class ContentPlannerCadenceTest extends TestCase
{
    private function config(): FunnelConfig
    {
        return new FunnelConfig(
            'Biz',
            'PEI',
            ['house washing'],
            'a@b.com',
            ['tiktok', 'instagram'],
            3,
            3,
            'Offer',
            'desc',
            'note',
            69900,
            'cad',
            'mailto:a@b.com',
            false,
            null,
            'https://x/thanks',
            'https://x/'
        );
    }

    public function test_safe_cadence_clamps_to_the_ceiling(): void
    {
        self::assertSame(ContentPlanner::SAFE_MAX_PER_DAY, ContentPlanner::safeCadence(50));
        self::assertSame(ContentPlanner::SAFE_MAX_PER_DAY, ContentPlanner::safeCadence(ContentPlanner::SAFE_MAX_PER_DAY));
        self::assertSame(4, ContentPlanner::safeCadence(4));
        self::assertSame(1, ContentPlanner::safeCadence(0));
        self::assertSame(1, ContentPlanner::safeCadence(-9));
    }

    public function test_plan_for_days_never_exceeds_the_safe_ceiling(): void
    {
        $posts = (new ContentPlanner($this->config()))->planForDays(2, 50, 1_700_000_000);

        // 2 days * capped 5/day = 10, not 100.
        self::assertCount(2 * ContentPlanner::SAFE_MAX_PER_DAY, $posts);
    }

    public function test_safe_cadence_leaves_reasonable_values_untouched(): void
    {
        $posts = (new ContentPlanner($this->config()))->planForDays(3, 4, 1_700_000_000);

        self::assertCount(12, $posts);
    }
}
