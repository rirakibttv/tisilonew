<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'checkout_reference')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('checkout_reference', 64)->nullable();
            });
        }
        if (! Schema::hasIndex('orders', 'orders_checkout_reference_unique')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unique('checkout_reference');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['checkout_reference']);
            $table->dropColumn('checkout_reference');
        });
    }
};
