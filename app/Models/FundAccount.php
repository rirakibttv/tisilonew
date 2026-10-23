<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundAccount extends Model
{
    protected $fillable = ['name', 'type', 'account_number', 'opening_balance', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FundTransaction::class);
    }

    public function getBalanceAttribute(): float
    {
        $credits = (float) $this->transactions()->where('direction', 'in')->sum('amount');
        $debits = (float) $this->transactions()->where('direction', 'out')->sum('amount');

        return (float) $this->opening_balance + $credits - $debits;
    }
}
