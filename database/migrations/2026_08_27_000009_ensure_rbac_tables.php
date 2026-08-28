<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rbac_roles')) {
            Schema::create('rbac_roles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rbac_permissions')) {
            Schema::create('rbac_permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('group')->default('General')->index();
                $table->text('description')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rbac_permission_role')) {
            Schema::create('rbac_permission_role', function (Blueprint $table) {
                $table->foreignId('role_id')->constrained('rbac_roles')->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained('rbac_permissions')->cascadeOnDelete();
                $table->primary(['role_id', 'permission_id']);
            });
        }

        if (! Schema::hasColumn('users', 'rbac_role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('rbac_role_id')
                    ->nullable()
                    ->after('role')
                    ->constrained('rbac_roles')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // The primary migration owns rollback of these shared compatibility tables.
    }
};
