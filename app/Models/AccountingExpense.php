<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountingExpense extends Model
{
    protected $table = 'accounting_expenses';

    protected $fillable = [
        'expense_number', 'category', 'payee', 'expense_date', 'amount', 'fund_account_id',
        'reference_number', 'description', 'created_by',
    ];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $expense): void {
            $expense->expense_number ??= 'EXP-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
            $expense->created_by ??= auth()->id();
        });

        static::saving(function (self $expense): void {
            if ((float) $expense->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Expense amount must be greater than zero.',
                ]);
            }
        });

        static::saved(fn (self $expense) => $expense->syncExpenseTransaction());
        static::deleted(fn (self $expense) => $expense->expenseTransaction()->delete());
    }

    public function fundAccount(): BelongsTo
    {
        return $this->belongsTo(FundAccount::class);
    }

    public function expenseTransaction(): MorphOne
    {
        return $this->morphOne(FundTransaction::class, 'source');
    }

    private function syncExpenseTransaction(): void
    {
        $this->expenseTransaction()->updateOrCreate([], [
            'fund_account_id' => $this->fund_account_id,
            'direction' => 'out',
            'amount' => $this->amount,
            'transaction_date' => $this->expense_date,
            'reference_number' => $this->reference_number ?: $this->expense_number,
            'created_by' => $this->created_by,
            'description' => $this->category.' · '.$this->expense_number,
        ]);
    }
}
