<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 180)->unique();
            // White-label branding the reseller presents to its sub-accounts.
            $table->string('brand_name', 150)->nullable();
            $table->string('custom_domain', 190)->nullable()->unique();
            $table->string('primary_color', 7)->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('status', 20)->default('Active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('agencies');
        Schema::enableForeignKeyConstraints();
    }
};
