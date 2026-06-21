<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Stripe event id is unique so webhooks can be processed idempotently.
            $table->string('stripe_event_id')->unique();
            $table->string('type');
            $table->string('checkout_session_id')->nullable()->index();
            $table->string('payment_intent_id')->nullable()->index();
            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('amount')->nullable(); // minor units (cents)
            $table->string('currency', 3)->nullable();
            $table->string('status')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
