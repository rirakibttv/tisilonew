<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 60);
            $table->string('contact_name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('district', 100);
            $table->string('upazila', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('status')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['vendor_id', 'code']);
            $table->index(['vendor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_warehouses');
    }
};
