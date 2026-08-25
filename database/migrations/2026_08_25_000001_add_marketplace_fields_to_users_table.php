<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->unique()->after('email');
            $table->string('role', 40)->default('customer')->index()->after('password');
            $table->string('status', 30)->default('active')->index()->after('role');
            $table->timestamp('last_login_at')->nullable()->after('status');
        });

        $bootstrapAdminId = DB::table('users')->min('id');

        if ($bootstrapAdminId !== null) {
            DB::table('users')
                ->where('id', $bootstrapAdminId)
                ->update(['role' => 'super_admin']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropColumn(['phone', 'role', 'status', 'last_login_at']);
        });
    }
};
