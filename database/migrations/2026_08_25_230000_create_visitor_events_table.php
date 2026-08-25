<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('visitor_id')->index();
            $table->char('session_id', 64)->nullable()->index();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 32)->index();
            $table->string('event_key', 100)->nullable()->unique();
            $table->string('path', 500)->nullable();
            $table->string('referrer_host')->nullable()->index();
            $table->string('traffic_source', 100)->nullable()->index();
            $table->string('traffic_medium', 100)->nullable();
            $table->string('traffic_campaign', 191)->nullable()->index();
            $table->string('traffic_content', 191)->nullable();
            $table->string('traffic_term', 191)->nullable();
            $table->string('click_source', 20)->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->decimal('value', 14, 2)->nullable();
            $table->string('device_type', 20)->nullable()->index();
            $table->string('browser', 40)->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['event_type', 'occurred_at']);
            $table->index(['visitor_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_events');
    }
};
