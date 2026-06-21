<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFunnelPagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('funnel_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 180);
            $table->string('type', 30)->default(config('custom.page.type_landing'))->comment(implode(',', config('custom.page.types')));
            $table->longText('content')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default(config('custom.page.status_active'))->comment(implode(',', config('custom.page.status')));
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['funnel_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('funnel_pages');
        Schema::enableForeignKeyConstraints();
    }
}
