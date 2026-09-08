<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_check_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 80);
            $table->text('mobile');
            $table->string('mobile_masked', 20);
            $table->char('mobile_hash', 64);
            $table->string('status', 20);
            $table->unsignedInteger('total_parcel')->default(0);
            $table->unsignedInteger('success_parcel')->default(0);
            $table->unsignedInteger('cancelled_parcel')->default(0);
            $table->decimal('success_ratio', 5, 2)->nullable();
            $table->string('risk_level', 30)->nullable();
            $table->text('message')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->index(['mobile_hash', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_check_histories');
    }
};
