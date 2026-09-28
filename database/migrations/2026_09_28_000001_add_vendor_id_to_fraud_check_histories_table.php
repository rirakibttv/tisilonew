<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fraud_check_histories', function (Blueprint $table): void {
            $table->foreignId('vendor_id')
                ->nullable()
                ->after('checked_by')
                ->constrained('vendors')
                ->nullOnDelete();
            $table->index(['vendor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('fraud_check_histories', function (Blueprint $table): void {
            $table->dropIndex(['vendor_id', 'created_at']);
            $table->dropConstrainedForeignId('vendor_id');
        });
    }
};
