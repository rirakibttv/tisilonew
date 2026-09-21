<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50)->default('Home');
            $table->string('recipient_name');
            $table->string('phone', 32);
            $table->text('address_line');
            $table->string('district', 100);
            $table->string('thana', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });

        Schema::create('customer_order_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index();
            $table->string('reason', 120);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('admin_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'type']);
            $table->index(['user_id', 'type', 'created_at']);
        });

        Schema::create('customer_payment_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('default_method', 30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payment_preferences');
        Schema::dropIfExists('customer_order_requests');
        Schema::dropIfExists('customer_addresses');
    }
};
