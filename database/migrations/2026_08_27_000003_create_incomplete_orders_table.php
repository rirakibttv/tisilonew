<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incomplete_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('converted_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('session_id')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable()->index();
            $table->string('customer_phone', 32)->nullable()->index();
            $table->text('customer_address')->nullable();
            $table->json('items')->nullable();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->string('status', 30)->default('incomplete')->index();
            $table->text('notes')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomplete_orders');
    }
};
