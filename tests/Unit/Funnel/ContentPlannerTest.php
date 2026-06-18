<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel;

use App\Funnel\Content\ContentPlanner;
use App\Funnel\FunnelConfig;
use App\Funnel\VideoPost;
use PHPUnit\Framework\TestCase;

final class ContentPlannerTest extends TestCase
{
    private function config(): FunnelConfig
    {
        return new FunnelConfig(
            businessName: 'Gulf Coast Painting PEI',
            location: 'Prince Edward Island',
            services: ['house washing', 'pressure washing'],
            contactEmail: 'test@example.com',
            platforms: ['tiktok', 'instagram', 'youtube'],
            offerName: 'Free Quote — Soft Wash + Power Wash',
            offerDescription: 'a light chemical wash that kills mildew, then a power wash and rinse',
            sizeNote: 'depending on the size of your home',
            fromPriceCents: 69900,
            currency: 'cad',
            bookingUrl: 'mailto:test@example.com',
            chargeUpfront: false,
            stripeSecret: null,
            checkoutSuccessUrl: 'https://x/ok',
            checkoutCancelUrl: 'https://x/no',
        );
    }

    public function test_it_plans_the_requested_number_of_posts(): void
    {
        $posts = (new ContentPlanner($this->config()))->plan(6, 1_000_000);

        self::assertCount(6, $posts);
    }

    public function test_every_second_post_is_a_business_video(): void
    {
        $posts = (new ContentPlanner($this->config()))->plan(6, 1_000_000);

        self::assertSame(VideoPost::TYPE_BUSINESS, $posts[0]->type);
        self::assertSame(VideoPost::TYPE_VIRAL, $posts[1]->type);
        self::assertSame(VideoPost::TYPE_BUSINESS, $posts[2]->type);
        self::assertSame(VideoPost::TYPE_VIRAL, $posts[3]->type);
    }

    public function test_business_posts_are_value_first_not_salesy(): void
    {
        $posts = (new ContentPlanner($this->config()))->plan(6, 1_000_000);

        foreach ($posts as $post) {
            if ($post->type !== VideoPost::TYPE_BUSINESS) {
                continue;
            }
            $haystack = strtolower($post->hook . ' ' . $post->script);
            self::assertStringNotContainsString('book now', $haystack);
            self::assertStringNotContainsString('buy', $haystack);
            // Should teach something: multi-step tip.
            self::assertGreaterThanOrEqual(2, substr_count($post->script, "\n"));
        }
    }

    public function test_business_captions_carry_the_free_quote_offer(): void
    {
        $post = (new ContentPlanner($this->config()))->plan(1, 1_000_000)[0];

        self::assertSame(VideoPost::TYPE_BUSINESS, $post->type);
        self::assertStringContainsString('from $699', $post->caption);
        self::assertStringContainsString('Free quote', $post->caption);
        self::assertStringContainsString('after', $post->caption);
    }

    public function test_posts_are_scheduled_at_increasing_times(): void
    {
        $posts = (new ContentPlanner($this->config()))->plan(4, 1_000_000, 3600);

        self::assertSame(1_000_000, $posts[0]->scheduledAt);
        self::assertSame(1_003_600, $posts[1]->scheduledAt);
        self::assertSame(1_010_800, $posts[3]->scheduledAt);
    }

    public function test_posts_carry_platforms_and_hashtags(): void
    {
        $post = (new ContentPlanner($this->config()))->plan(1, 1_000_000)[0];

        self::assertSame(['tiktok', 'instagram', 'youtube'], $post->platforms);
        self::assertNotEmpty($post->hashtags);
        self::assertContains('#pei', $post->hashtags);
    }
}
