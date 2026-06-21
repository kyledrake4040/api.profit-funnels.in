<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Funnel\Attribution\AttributionRecorder;
use App\Funnel\Attribution\AttributionStore;
use App\Funnel\Attribution\EloquentAttributionStore;
use App\Funnel\Attribution\JsonAttributionStore;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FunnelArtisanTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/funnel_artisan_' . uniqid() . '/attribution.json';
        config(['funnel.json_store_path' => $this->tmp, 'funnel.attribution_driver' => 'json']);
    }

    protected function tearDown(): void
    {
        @unlink($this->tmp);
        @rmdir(\dirname($this->tmp));
        parent::tearDown();
    }

    public function test_attribution_store_is_resolved_from_the_container_by_driver(): void
    {
        config(['funnel.attribution_driver' => 'json']);
        $this->assertInstanceOf(JsonAttributionStore::class, app(AttributionStore::class));

        config(['funnel.attribution_driver' => 'eloquent']);
        $this->assertInstanceOf(EloquentAttributionStore::class, app(AttributionStore::class));
    }

    public function test_funnel_report_command_renders_attribution(): void
    {
        // Seed through the same container-resolved store the command will use.
        $recorder = new AttributionRecorder(app(AttributionStore::class));
        $recorder->recordLead(['utm_source' => 'funnel', 'contact_id' => 'a']);
        $recorder->recordLead(['utm_source' => 'google', 'contact_id' => 'b']);
        $recorder->recordPaidInvoice(['customer_id' => 'a', 'amount' => 699.00]);

        $exit = Artisan::call('funnel:report', ['--days' => 30]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Funnel attribution', $output);
        $this->assertStringContainsString('$699.00', $output);
    }
}
