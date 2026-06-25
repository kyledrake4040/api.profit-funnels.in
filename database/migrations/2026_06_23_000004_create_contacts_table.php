<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('first_name', 120);
            $table->string('last_name', 120)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('company', 160)->nullable();
            $table->string('status', 30)->default('Lead');
            $table->string('source', 80)->nullable();
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('contacts');
        Schema::enableForeignKeyConstraints();
    }
};
