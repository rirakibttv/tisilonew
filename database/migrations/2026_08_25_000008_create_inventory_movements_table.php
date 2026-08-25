<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_stock_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40)->index();
            $table->integer('quantity_delta')->default(0);
            $table->integer('reserved_delta')->default(0);
            $table->unsignedInteger('quantity_before');
            $table->unsignedInteger('quantity_after');
            $table->unsignedInteger('reserved_before');
            $table->unsignedInteger('reserved_after');
            $table->nullableMorphs('reference');
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['inventory_stock_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
