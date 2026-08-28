<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_records', function (Blueprint $table): void {
            $table->id();
            $table->string('module', 80)->index();
            $table->string('section', 80)->index();
            $table->string('title');
            $table->string('reference')->nullable()->index();
            $table->string('status', 40)->default('active')->index();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('contact')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['module', 'section', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_records');
    }
};
