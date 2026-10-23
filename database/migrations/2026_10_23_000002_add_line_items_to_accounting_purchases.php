<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('accounting_purchases', 'line_items')) {
            Schema::table('accounting_purchases', function (Blueprint $table): void {
                $table->json('line_items')->nullable()->after('invoice_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('accounting_purchases', 'line_items')) {
            Schema::table('accounting_purchases', function (Blueprint $table): void {
                $table->dropColumn('line_items');
            });
        }
    }
};
