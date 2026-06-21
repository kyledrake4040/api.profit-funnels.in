<?php

declare(strict_types=1);

namespace App\Funnel;

/**
 * Central configuration for the funnel engine.
 *
 * Reads from environment variables so the same code runs in "dry-run" mode
 * with zero setup, and goes live the moment real credentials are provided.
 */
final class FunnelConfig
{
    public function __construct(
        public readonly string $businessName,
        public readonly string $location,
        /** @var string[] */
        public readonly array $services,
        public readonly string $contactEmail,
        /** @var string[] list of platform keys: tiktok, instagram, youtube */
        public readonly array $platforms,
        /** Posts/day per social platform (via GHL). High values risk bans. */
        public readonly int $socialsPerDay,
        /** Before/after posts/day on Google Business Profile. */
        public readonly int $gbpPerDay,
        public readonly string $offerName,
        /** Plain-language description of the job. */
        public readonly string $offerDescription,
        /** Why the price varies. */
        public readonly string $sizeNote,
        /** Minimum job price ("jobs from $X"). NOT charged upfront. */
        public readonly int $fromPriceCents,
        public readonly string $currency,
        /** Where a "book a free quote" CTA points (booking form / mailto). */
        public readonly string $bookingUrl,
        /** When false (default) the funnel never charges before the work. */
        public readonly bool $chargeUpfront,
        public readonly ?string $stripeSecret,
        public readonly string $checkoutSuccessUrl,
        public readonly string $checkoutCancelUrl,
    ) {
    }

    public static function fromEnv(): self
    {
        $env = static fn (string $key, ?string $default = null): ?string => getenv($key) !== false ? (string) getenv($key) : $default;

        $services = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $env('FUNNEL_SERVICES', 'house washing,pressure washing,exterior painting'))
        )));

        $platforms = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $env('FUNNEL_PLATFORMS', 'tiktok,instagram,youtube'))
        )));

        $contactEmail = (string) $env('FUNNEL_CONTACT_EMAIL', 'gulfcoastpaintingpei@gmail.com');

        return new self(
            businessName: (string) $env('FUNNEL_BUSINESS_NAME', 'Gulf Coast Painting PEI'),
            location: (string) $env('FUNNEL_LOCATION', 'Prince Edward Island'),
            services: $services,
            contactEmail: $contactEmail,
            platforms: $platforms,
            socialsPerDay: self::requirePositiveInt('FUNNEL_POSTS_PER_DAY', $env('FUNNEL_POSTS_PER_DAY', '3')),
            gbpPerDay: self::requirePositiveInt('FUNNEL_GBP_POSTS_PER_DAY', $env('FUNNEL_GBP_POSTS_PER_DAY', '3')),
            offerName: (string) $env('FUNNEL_OFFER_NAME', 'Free Quote — Soft Wash + Power Wash'),
            offerDescription: (string) $env(
                'FUNNEL_OFFER_DESC',
                'a light chemical wash that kills mildew and lifts dirt, then a power wash and rinse'
            ),
            sizeNote: (string) $env('FUNNEL_SIZE_NOTE', 'depending on the size of your home'),
            fromPriceCents: self::requirePositiveInt('FUNNEL_FROM_PRICE_CENTS', $env('FUNNEL_FROM_PRICE_CENTS', '69900')),
            currency: (string) $env('FUNNEL_CURRENCY', 'cad'),
            bookingUrl: (string) $env(
                'FUNNEL_BOOKING_URL',
                'mailto:' . $contactEmail . '?subject=Free%20Quote%20-%20House%20Wash%20%26%20Power%20Wash'
            ),
            chargeUpfront: filter_var($env('FUNNEL_CHARGE_UPFRONT', 'false'), FILTER_VALIDATE_BOOL),
            stripeSecret: $env('STRIPE_SECRET'),
            checkoutSuccessUrl: (string) $env('FUNNEL_SUCCESS_URL', 'https://example.com/thanks'),
            checkoutCancelUrl: (string) $env('FUNNEL_CANCEL_URL', 'https://example.com/'),
        );
    }

    /**
     * Validate that a configuration value is a positive integer, failing fast
     * with a clear message on boot instead of silently clamping or coercing a
     * bad value (e.g. "abc", "0" or "-5") into something unexpected.
     */
    private static function requirePositiveInt(string $key, ?string $raw): int
    {
        $value = (string) $raw;
        if (! ctype_digit($value) || (int) $value < 1) {
            throw new \RuntimeException(
                "Invalid {$key}: expected a positive integer, got \"{$value}\"."
            );
        }

        return (int) $value;
    }

    public function offer(): BookingOffer
    {
        return new BookingOffer(
            name: $this->offerName,
            fromPriceCents: $this->fromPriceCents,
            currency: $this->currency,
            bookingUrl: $this->bookingUrl,
            description: $this->offerDescription,
            sizeNote: $this->sizeNote,
        );
    }
}
