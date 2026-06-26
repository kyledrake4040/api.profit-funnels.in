<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "service_jobs" (not "jobs") to avoid colliding with Laravel's queue
        // jobs table. This is the Jobber-style field-service work order.
        Schema::create('service_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('Scheduled');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->decimal('value', 12, 2)->default(0);
            $table->string('currency', 3)->default('cad');
            $table->string('address', 255)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('service_jobs');
        Schema::enableForeignKeyConstraints();
    }
};
