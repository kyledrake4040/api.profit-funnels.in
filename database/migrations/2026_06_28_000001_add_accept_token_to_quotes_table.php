<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('accept_token', 64)->nullable()->unique()->after('number');
        });

        // Backfill tokens for any existing quotes.
        foreach (\App\Models\Quote::withTrashed()->whereNull('accept_token')->get() as $quote) {
            $quote->accept_token = Str::random(48);
            $quote->saveQuietly();
        }
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('accept_token');
        });
    }
};
