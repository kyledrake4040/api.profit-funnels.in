<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel;

use App\Funnel\Payments\FakePaymentGateway;
use App\Funnel\Payments\StripePaymentGateway;
use PHPUnit\Framework\TestCase;

final class PaymentsTest extends TestCase
{
    public function test_fake_gateway_returns_a_demo_checkout_link(): void
    {
        $link = (new FakePaymentGateway())->createCheckout('Quote', 4900, 'cad', 'https://x/ok', 'https://x/no');

        self::assertFalse($link->live);
        self::assertSame(4900, $link->amountCents);
        self::assertStringContainsString('checkout.stripe.com', $link->url);
    }

    public function test_stripe_build_fields_are_well_formed(): void
    {
        $gateway = new StripePaymentGateway('sk_test_abc');
        $fields = $gateway->buildFields('Quote', 4900, 'cad', 'https://x/ok', 'https://x/no');

        self::assertSame('payment', $fields['mode']);
        self::assertSame('4900', $fields['line_items[0][price_data][unit_amount]']);
        self::assertSame('cad', $fields['line_items[0][price_data][currency]']);
        self::assertSame('Quote', $fields['line_items[0][price_data][product_data][name]']);
        self::assertSame('https://x/ok', $fields['success_url']);
    }

    public function test_stripe_create_checkout_parses_session_url(): void
    {
        $transport = static fn (string $url, array $fields, string $secret): array => [
            'status' => 200,
            'body' => json_encode(['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_123']),
        ];

        $link = (new StripePaymentGateway('sk_test_abc', $transport))
            ->createCheckout('Quote', 4900, 'cad', 'https://x/ok', 'https://x/no');

        self::assertSame('cs_test_123', $link->id);
        self::assertStringContainsString('cs_test_123', $link->url);
        self::assertFalse($link->live);
    }

    public function test_stripe_surfaces_api_errors(): void
    {
        $transport = static fn (): array => [
            'status' => 400,
            'body' => json_encode(['error' => ['message' => 'Invalid API Key']]),
        ];

        $this->expectExceptionMessage('Invalid API Key');

        (new StripePaymentGateway('sk_test_bad', $transport))
            ->createCheckout('Quote', 4900, 'cad', 'https://x/ok', 'https://x/no');
    }

    public function test_stripe_build_subscription_fields_are_well_formed(): void
    {
        $gateway = new StripePaymentGateway('sk_test_abc');
        $fields = $gateway->buildSubscriptionFields('ProfitProof Pro', 24900, 'usd', 'month', 'https://x/ok', 'https://x/no');

        self::assertSame('subscription', $fields['mode']);
        self::assertSame('24900', $fields['line_items[0][price_data][unit_amount]']);
        self::assertSame('month', $fields['line_items[0][price_data][recurring][interval]']);
        self::assertSame('ProfitProof Pro', $fields['line_items[0][price_data][product_data][name]']);
    }

    public function test_stripe_create_subscription_checkout_parses_session_url(): void
    {
        $transport = static fn (string $url, array $fields, string $secret): array => [
            'status' => 200,
            'body' => json_encode(['id' => 'cs_sub_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_sub_1']),
        ];

        $link = (new StripePaymentGateway('sk_test_abc', $transport))
            ->createSubscriptionCheckout('ProfitProof Pro', 24900, 'usd', 'month', 'https://x/ok', 'https://x/no');

        self::assertSame('cs_sub_1', $link->id);
        self::assertSame(24900, $link->amountCents);
    }
}
