<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained('automations')->cascadeOnDelete();
            $table->string('type', 60);
            $table->json('config')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['automation_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('automation_actions');
        Schema::enableForeignKeyConstraints();
    }
};
