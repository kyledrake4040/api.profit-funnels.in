<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained('pipelines')->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained('pipeline_stages')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('name', 180);
            $table->decimal('value', 12, 2)->default(0);
            $table->string('currency', 3)->default('cad');
            $table->string('status', 20)->default('Open');
            $table->date('expected_close_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['pipeline_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('opportunities');
        Schema::enableForeignKeyConstraints();
    }
};
