<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('service_jobs')->nullOnDelete();
            $table->string('number', 40);
            $table->string('status', 20)->default('Draft');
            $table->string('currency', 3)->default('cad');
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('quotes');
        Schema::enableForeignKeyConstraints();
    }
};
