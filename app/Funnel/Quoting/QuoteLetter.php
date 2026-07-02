<?php

declare(strict_types=1);

namespace App\Funnel\Quoting;

use App\Funnel\FunnelConfig;

/**
 * Formats a Quote into a warm, ready-to-send written quote in the business's
 * voice — the message a customer receives. Keeps the promises the funnel makes:
 * no payment today, you only pay once the work is done.
 */
final class QuoteLetter
{
    private const SERVICE_LABEL = [
        QuoteRequest::SERVICE_SOFT_WASH => 'exterior soft wash',
        QuoteRequest::SERVICE_POWER_WASH => 'exterior power wash',
        QuoteRequest::SERVICE_COMBO => 'soft wash + power wash',
        QuoteRequest::SERVICE_PAINTING => 'exterior painting',
    ];

    private const SERVICE_INCLUDES = [
        QuoteRequest::SERVICE_SOFT_WASH => 'a low-pressure chemical wash that kills mildew and algae and lifts dirt, then a gentle rinse',
        QuoteRequest::SERVICE_POWER_WASH => 'a full power wash and rinse of the exterior',
        QuoteRequest::SERVICE_COMBO => 'a light chemical soft wash that kills mildew and lifts dirt, then a power wash and rinse',
        QuoteRequest::SERVICE_PAINTING => 'surface prep, and exterior painting with quality materials',
    ];

    public static function compose(FunnelConfig $config, QuoteRequest $request, Quote $quote): string
    {
        $name = trim((string) $request->customerName) !== '' ? $request->customerName : 'there';
        $service = self::SERVICE_LABEL[$request->service] ?? str_replace('_', ' ', $request->service);
        $includes = self::SERVICE_INCLUDES[$request->service] ?? $service;

        $lines = [];
        $lines[] = "Hi {$name},";
        $lines[] = '';
        $where = trim((string) $request->address) !== '' ? " at {$request->address}" : '';
        $lines[] = "Thanks for reaching out to {$config->businessName}. Here's your quote for a {$service}{$where}:";
        $lines[] = '';
        $lines[] = '  Price: ' . $quote->amount($quote->totalCents);
        if (! $quote->minimumApplied) {
            // Show the market band so the price visibly sits in the middle.
            $lines[] = '  (Comparable jobs on PEI run ' . $quote->marketRangeLabel() . ' — we price fairly in the middle.)';
        }
        $lines[] = '';
        $lines[] = "This includes {$includes}.";
        $lines[] = '';
        $lines[] = 'There is no payment today. You only pay once the work is done and you\'re happy with it.';

        if ($quote->confidence !== 'high') {
            $lines[] = '';
            $lines[] = 'Please note: this is an estimate based on the details provided. I\'ll confirm the exact '
                . 'price with a quick measure — it won\'t change much.';
        }

        $lines[] = '';
        $lines[] = 'Want me to book you in? Just reply and we\'ll find a time.';
        $lines[] = '';
        $lines[] = 'Thanks,';
        $lines[] = $config->businessName;
        $lines[] = $config->contactEmail;

        return implode(PHP_EOL, $lines);
    }
}
