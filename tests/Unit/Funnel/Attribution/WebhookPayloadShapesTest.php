<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel\Attribution;

use App\Funnel\Attribution\AttributionRecorder;
use App\Funnel\Attribution\JsonAttributionStore;
use PHPUnit\Framework\TestCase;

/**
 * The recorder must understand the real nested shapes GoHighLevel and
 * QuickBooks send, not just flat keys.
 */
final class WebhookPayloadShapesTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/funnel_shapes_' . uniqid() . '/attribution.json';
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
        return new AttributionRecorder(new JsonAttributionStore($this->path), fn (): int => 1_700_000_000);
    }

    public function test_gohighlevel_nested_attribution_source_is_read(): void
    {
        // Shape GHL actually posts: UTM under attributionSource, id under contact.
        $row = $this->recorder()->recordLead([
            'type' => 'ContactCreate',
            'contact' => [
                'id' => 'ghl-contact-77',
                'email' => 'jane@example.com',
            ],
            'attributionSource' => [
                'utmSource' => 'funnel',
                'utmMedium' => 'tiktok',
                'utmCampaign' => 'soft-wash',
                'sessionSource' => 'tiktok',
            ],
        ]);

        self::assertTrue($row->isFunnel());
        self::assertSame('funnel', $row->utmSource);
        self::assertSame('tiktok', $row->utmMedium);
        self::assertSame('soft-wash', $row->utmCampaign);
        self::assertSame('ghl-contact-77', $row->leadId);
    }

    public function test_gohighlevel_custom_data_values_are_read(): void
    {
        $row = $this->recorder()->recordLead([
            'contact_id' => 'c-1',
            'customData' => [
                'utm_source' => 'funnel',
                'post_id' => 'post_042',
                'platform' => 'instagram',
            ],
        ]);

        self::assertTrue($row->isFunnel());
        self::assertSame('post_042', $row->postId);
        self::assertSame('instagram', $row->platform);
        self::assertSame('c-1', $row->leadId);
    }

    public function test_quickbooks_customer_ref_and_total_amt_update_revenue(): void
    {
        $recorder = $this->recorder();
        $recorder->recordLead(['utm_source' => 'funnel', 'contact_id' => 'cust-9']);

        $updated = $recorder->recordPaidInvoice([
            'CustomerRef' => ['value' => 'cust-9', 'name' => 'Jane Doe'],
            'TotalAmt' => 699.0,
        ]);

        self::assertSame(1, $updated);
        $rows = (new JsonAttributionStore($this->path))->allForLead('cust-9');
        self::assertSame(69900, $rows[0]->revenueCents);
    }

    public function test_flat_keys_still_work(): void
    {
        $row = $this->recorder()->recordLead([
            'utm_source' => 'funnel',
            'contact_id' => 'flat-1',
            'post_id' => 'p1',
        ]);

        self::assertTrue($row->isFunnel());
        self::assertSame('flat-1', $row->leadId);
        self::assertSame('p1', $row->postId);
    }
}
