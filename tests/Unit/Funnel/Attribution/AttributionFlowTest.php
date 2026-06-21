<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel\Attribution;

use App\Funnel\Attribution\AttributionRecorder;
use App\Funnel\Attribution\AttributionReport;
use App\Funnel\Attribution\JsonAttributionStore;
use PHPUnit\Framework\TestCase;

final class AttributionFlowTest extends TestCase
{
    private string $path;
    private int $now = 1_700_000_000;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/funnel_attr_' . uniqid() . '/attribution.json';
    }

    protected function tearDown(): void
    {
        foreach (glob(\dirname($this->path) . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir(\dirname($this->path));
    }

    private function recorder(): AttributionRecorder
    {
        return new AttributionRecorder(
            new JsonAttributionStore($this->path),
            fn (): int => $this->now
        );
    }

    public function test_funnel_lead_is_recorded_and_flagged(): void
    {
        $row = $this->recorder()->recordLead([
            'utm_source' => 'funnel',
            'utm_medium' => 'tiktok',
            'utm_campaign' => 'soft-wash',
            'post_id' => 'post_001',
            'platform' => 'tiktok',
            'contact_id' => 'lead-1',
        ]);

        self::assertTrue($row->isFunnel());
        self::assertSame('lead-1', $row->leadId);
        self::assertSame('post_001', $row->postId);
        self::assertNull($row->revenueCents);
    }

    public function test_non_funnel_lead_is_recorded_as_other(): void
    {
        $row = $this->recorder()->recordLead([
            'utm_source' => 'google',
            'contact_id' => 'lead-2',
        ]);

        self::assertFalse($row->isFunnel());
        self::assertSame('google', $row->utmSource);
    }

    public function test_lead_without_utm_source_defaults_to_direct(): void
    {
        $row = $this->recorder()->recordLead(['contact_id' => 'lead-3']);

        self::assertSame('direct', $row->utmSource);
        self::assertFalse($row->isFunnel());
    }

    public function test_paid_invoice_updates_revenue_for_the_lead(): void
    {
        $recorder = $this->recorder();
        $recorder->recordLead(['utm_source' => 'funnel', 'contact_id' => 'lead-1']);

        $updated = $recorder->recordPaidInvoice(['customer_id' => 'lead-1', 'amount' => 699.00]);

        self::assertSame(1, $updated);
        $rows = (new JsonAttributionStore($this->path))->allForLead('lead-1');
        self::assertSame(69900, $rows[0]->revenueCents);
    }

    public function test_paid_invoice_for_unknown_lead_updates_nothing(): void
    {
        $updated = $this->recorder()->recordPaidInvoice(['customer_id' => 'ghost', 'amount' => 100]);

        self::assertSame(0, $updated);
    }

    public function test_report_splits_funnel_vs_other_within_window(): void
    {
        $recorder = $this->recorder();
        // Two funnel leads, one converts; one other lead.
        $recorder->recordLead(['utm_source' => 'funnel', 'contact_id' => 'a']);
        $recorder->recordLead(['utm_source' => 'funnel', 'contact_id' => 'b']);
        $recorder->recordLead(['utm_source' => 'google', 'contact_id' => 'c']);
        $recorder->recordPaidInvoice(['customer_id' => 'a', 'amount' => 1000.00]);

        // An old lead outside the 7-day window must be excluded.
        $this->now -= 30 * 86400;
        $recorder->recordLead(['utm_source' => 'funnel', 'contact_id' => 'old']);
        $this->now += 30 * 86400;

        $summary = (new AttributionReport(new JsonAttributionStore($this->path)))
            ->summarize(7, $this->now);

        self::assertSame(2, $summary['funnel']['leads']);
        self::assertSame(100000, $summary['funnel']['revenue_cents']);
        self::assertSame(1, $summary['other']['leads']);
        self::assertSame(0, $summary['other']['revenue_cents']);
    }

    public function test_render_produces_readable_output(): void
    {
        $this->recorder()->recordLead(['utm_source' => 'funnel', 'contact_id' => 'a']);

        $out = (new AttributionReport(new JsonAttributionStore($this->path)))->render(7, $this->now);

        self::assertStringContainsString('Funnel attribution', $out);
        self::assertStringContainsString('funnel', $out);
        self::assertStringContainsString('revenue', $out);
    }
}
