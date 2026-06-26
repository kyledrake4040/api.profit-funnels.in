<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('pay_token', 64)->nullable()->unique()->after('number');
        });

        // Backfill tokens for any existing invoices.
        foreach (\App\Models\Invoice::withTrashed()->whereNull('pay_token')->get() as $invoice) {
            $invoice->pay_token = Str::random(48);
            $invoice->saveQuietly();
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('pay_token');
        });
    }
};
