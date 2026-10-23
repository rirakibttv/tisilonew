<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingSupplier extends Model
{
    protected $table = 'accounting_suppliers';

    protected $fillable = ['name', 'contact_person', 'phone', 'email', 'address', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(AccountingPurchase::class, 'supplier_id');
    }

    public function getOutstandingBalanceAttribute(): float
    {
        return (float) $this->purchases()->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as due')->value('due');
    }
}
