<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 32)->nullable()->index();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('fund_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type', 30)->default('cash')->index();
            $table->string('account_number')->nullable();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_purchases', function (Blueprint $table): void {
            $table->id();
            $table->string('purchase_number', 40)->unique();
            $table->foreignId('supplier_id')->constrained('accounting_suppliers')->restrictOnDelete();
            $table->date('purchase_date')->index();
            $table->string('invoice_number')->nullable()->index();
            $table->decimal('total_amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->foreignId('payment_account_id')->nullable()->constrained('fund_accounts')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('accounting_expenses', function (Blueprint $table): void {
            $table->id();
            $table->string('expense_number', 40)->unique();
            $table->string('category');
            $table->string('payee')->nullable();
            $table->date('expense_date')->index();
            $table->decimal('amount', 14, 2);
            $table->foreignId('fund_account_id')->constrained('fund_accounts')->restrictOnDelete();
            $table->string('reference_number')->nullable()->index();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fund_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fund_account_id')->constrained()->restrictOnDelete();
            $table->string('direction', 10)->index();
            $table->decimal('amount', 14, 2);
            $table->date('transaction_date')->index();
            $table->string('reference_number')->nullable();
            $table->string('source_type', 191)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
            $table->index(['fund_account_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_transactions');
        Schema::dropIfExists('accounting_expenses');
        Schema::dropIfExists('accounting_purchases');
        Schema::dropIfExists('fund_accounts');
        Schema::dropIfExists('accounting_suppliers');
    }
};
