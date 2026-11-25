<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'parcel_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('parcel_id')->nullable()->after('tracking_number')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'parcel_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropIndex(['parcel_id']);
                $table->dropColumn('parcel_id');
            });
        }
    }
};
