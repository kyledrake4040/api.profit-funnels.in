<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel;

use App\Funnel\FunnelConfig;
use PHPUnit\Framework\TestCase;

/**
 * FunnelConfig should fail fast on boot when a numeric env value is invalid,
 * rather than silently clamping or coercing it.
 */
final class FunnelConfigValidationTest extends TestCase
{
    /** @var string[] */
    private array $keys = [
        'FUNNEL_POSTS_PER_DAY',
        'FUNNEL_GBP_POSTS_PER_DAY',
        'FUNNEL_FROM_PRICE_CENTS',
    ];

    protected function setUp(): void
    {
        $this->clearEnv();
    }

    protected function tearDown(): void
    {
        $this->clearEnv();
    }

    private function clearEnv(): void
    {
        foreach ($this->keys as $key) {
            putenv($key);
        }
    }

    public function test_valid_values_parse(): void
    {
        putenv('FUNNEL_POSTS_PER_DAY=4');
        putenv('FUNNEL_GBP_POSTS_PER_DAY=2');
        putenv('FUNNEL_FROM_PRICE_CENTS=50000');

        $config = FunnelConfig::fromEnv();

        self::assertSame(4, $config->socialsPerDay);
        self::assertSame(2, $config->gbpPerDay);
        self::assertSame(50000, $config->fromPriceCents);
    }

    public function test_defaults_are_used_when_unset(): void
    {
        $config = FunnelConfig::fromEnv();

        self::assertGreaterThanOrEqual(1, $config->socialsPerDay);
        self::assertGreaterThanOrEqual(1, $config->gbpPerDay);
        self::assertGreaterThanOrEqual(1, $config->fromPriceCents);
    }

    public function test_zero_is_rejected(): void
    {
        putenv('FUNNEL_POSTS_PER_DAY=0');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FUNNEL_POSTS_PER_DAY');

        FunnelConfig::fromEnv();
    }

    public function test_negative_is_rejected(): void
    {
        putenv('FUNNEL_GBP_POSTS_PER_DAY=-5');

        $this->expectException(\RuntimeException::class);

        FunnelConfig::fromEnv();
    }

    public function test_non_numeric_is_rejected(): void
    {
        putenv('FUNNEL_FROM_PRICE_CENTS=abc');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FUNNEL_FROM_PRICE_CENTS');

        FunnelConfig::fromEnv();
    }
}
