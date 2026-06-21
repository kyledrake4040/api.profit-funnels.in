<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production-grade attribution table. The engine ships with a zero-infra JSON
 * store (JsonAttributionStore) that mirrors these columns; this table is the
 * database-backed equivalent for deployments that prefer SQL.
 */
class CreateFunnelAttributionTable extends Migration
{
    public function up()
    {
        Schema::create('funnel_attribution', function (Blueprint $table) {
            $table->id();
            $table->string('post_id')->index();
            $table->string('platform')->nullable();
            $table->string('utm_source')->index();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('lead_id')->nullable()->index();
            $table->bigInteger('revenue_cents')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('funnel_attribution');
    }
}
