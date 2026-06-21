<?php

declare(strict_types=1);

namespace App\Funnel\Attribution;

/**
 * Summarises attribution over a recent window, split by funnel vs. other
 * traffic. This is the conversion guardrail the weekly monitoring cron reads:
 * leads and revenue the funnel produced versus everything else.
 */
final class AttributionReport
{
    public function __construct(private readonly AttributionStore $store)
    {
    }

    /**
     * @return array{
     *   days:int,
     *   funnel:array{leads:int,revenue_cents:int},
     *   other:array{leads:int,revenue_cents:int}
     * }
     */
    public function summarize(int $days, ?int $now = null): array
    {
        $now ??= time();
        $since = $now - ($days * 86400);

        $funnel = ['leads' => 0, 'revenue_cents' => 0];
        $other = ['leads' => 0, 'revenue_cents' => 0];

        foreach ($this->store->recordedSince($since) as $row) {
            $bucket = $row->isFunnel() ? 'funnel' : 'other';
            ${$bucket}['leads']++;
            ${$bucket}['revenue_cents'] += $row->revenueCents ?? 0;
        }

        return ['days' => $days, 'funnel' => $funnel, 'other' => $other];
    }

    public function render(int $days, ?int $now = null): string
    {
        $s = $this->summarize($days, $now);
        $money = static fn (int $cents): string => '$' . number_format($cents / 100, 2);

        $lines = [];
        $lines[] = "Funnel attribution — last {$days} day(s)";
        $lines[] = str_repeat('-', 44);
        $lines[] = sprintf('  %-8s %8s %14s', '', 'leads', 'revenue');
        $lines[] = sprintf('  %-8s %8d %14s', 'funnel', $s['funnel']['leads'], $money($s['funnel']['revenue_cents']));
        $lines[] = sprintf('  %-8s %8d %14s', 'other', $s['other']['leads'], $money($s['other']['revenue_cents']));

        $totalLeads = $s['funnel']['leads'] + $s['other']['leads'];
        $share = $totalLeads > 0 ? round($s['funnel']['leads'] / $totalLeads * 100) : 0;
        $lines[] = str_repeat('-', 44);
        $lines[] = "  funnel share of leads: {$share}%";

        return implode("\n", $lines) . "\n";
    }
}
