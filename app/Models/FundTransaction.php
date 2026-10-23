<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\ValidationException;

class FundTransaction extends Model
{
    protected $fillable = [
        'fund_account_id', 'direction', 'amount', 'transaction_date', 'reference_number',
        'source_type', 'source_id', 'created_by', 'description',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transaction_date' => 'date'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $transaction): void {
            if (! in_array($transaction->direction, ['in', 'out'], true) || (float) $transaction->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Choose a valid fund direction and an amount greater than zero.',
                ]);
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FundAccount::class, 'fund_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
