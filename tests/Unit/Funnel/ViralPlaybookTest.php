<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel;

use App\Funnel\Content\ViralPlaybook;
use PHPUnit\Framework\TestCase;

final class ViralPlaybookTest extends TestCase
{
    public function test_hooks_are_tight_enough_to_land_in_three_seconds(): void
    {
        $playbook = new ViralPlaybook();

        for ($i = 0; $i < 12; $i++) {
            $hook = $playbook->hookFor('pressure washing', $i);
            self::assertTrue(
                ViralPlaybook::hookIsTight($hook),
                "Hook too long ({$hook})"
            );
        }
    }

    public function test_it_injects_the_topic_into_the_hook(): void
    {
        $hook = (new ViralPlaybook())->hookFor('mildew', 0);

        self::assertStringContainsString('mildew', $hook);
    }

    public function test_retention_brief_includes_caption_and_length_guidance(): void
    {
        $brief = (new ViralPlaybook())->retentionBrief();

        self::assertStringContainsStringIgnoringCase('caption', $brief);
        self::assertStringContainsString((string) ViralPlaybook::TARGET_MIN_SECONDS, $brief);
    }

    public function test_hook_formulas_rotate(): void
    {
        $playbook = new ViralPlaybook();

        self::assertNotSame(
            $playbook->formulaType(0),
            $playbook->formulaType(1)
        );
    }
}
