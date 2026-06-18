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
        public readonly string $offerName,
        public readonly int $offerAmountCents,
        public readonly string $currency,
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
            explode(',', (string) $env('FUNNEL_SERVICES', 'golf course painting,pressure washing'))
        )));

        $platforms = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $env('FUNNEL_PLATFORMS', 'tiktok,instagram,youtube'))
        )));

        return new self(
            businessName: (string) $env('FUNNEL_BUSINESS_NAME', 'Gulf Coast Painting PEI'),
            location: (string) $env('FUNNEL_LOCATION', 'Prince Edward Island'),
            services: $services,
            contactEmail: (string) $env('FUNNEL_CONTACT_EMAIL', 'gulfcoastpaintingpei@gmail.com'),
            platforms: $platforms,
            offerName: (string) $env('FUNNEL_OFFER_NAME', 'Free Pressure Washing Quote — Priority Booking'),
            offerAmountCents: (int) $env('FUNNEL_OFFER_AMOUNT_CENTS', '4900'),
            currency: (string) $env('FUNNEL_CURRENCY', 'cad'),
            stripeSecret: $env('STRIPE_SECRET'),
            checkoutSuccessUrl: (string) $env('FUNNEL_SUCCESS_URL', 'https://example.com/thanks'),
            checkoutCancelUrl: (string) $env('FUNNEL_CANCEL_URL', 'https://example.com/'),
        );
    }
}
