<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 40)->index();
            $table->string('gateway_payment_id', 191)->nullable()->unique();
            $table->string('transaction_id', 191)->nullable()->index();
            $table->string('merchant_invoice_number', 191)->index();
            $table->string('status', 40)->default('initiating')->index();
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('BDT');
            $table->text('redirect_url')->nullable();
            $table->json('gateway_response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'gateway']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
