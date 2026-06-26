<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One published micro-site per account — a simple business website for
        // clients who don't have one, with a lead form that feeds the CRM.
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained('accounts')->cascadeOnDelete();
            $table->string('slug', 80)->unique();
            $table->string('business_name', 160);
            $table->string('headline', 200)->nullable();
            $table->text('about')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('city', 120)->nullable();
            $table->json('services')->nullable();
            $table->string('theme_color', 7)->nullable();
            $table->boolean('published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('sites');
        Schema::enableForeignKeyConstraints();
    }
};
