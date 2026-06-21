<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel;

use App\Funnel\FunnelConfig;
use PHPUnit\Framework\TestCase;

/**
 * Guards the default social posting cadence. A high default (e.g. 50/day per
 * platform) risks spam flags, shadowbans and account suspension, so the
 * out-of-the-box value must stay low.
 */
final class FunnelCadenceDefaultTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('FUNNEL_POSTS_PER_DAY');
    }

    public function testDefaultSocialsPerDayIsConservative(): void
    {
        $config = FunnelConfig::fromEnv();

        $this->assertSame(2, $config->socialsPerDay);
    }

    public function testExplicitValueStillOverridesDefault(): void
    {
        putenv('FUNNEL_POSTS_PER_DAY=4');

        $config = FunnelConfig::fromEnv();

        $this->assertSame(4, $config->socialsPerDay);

        putenv('FUNNEL_POSTS_PER_DAY');
    }
}
