<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountingPurchase extends Model
{
    protected $table = 'accounting_purchases';

    protected $fillable = [
        'purchase_number', 'supplier_id', 'purchase_date', 'invoice_number', 'total_amount',
        'line_items', 'paid_amount', 'payment_account_id', 'description', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'line_items' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $purchase): void {
            $purchase->purchase_number ??= 'PUR-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
            $purchase->created_by ??= auth()->id();
        });

        static::saving(function (self $purchase): void {
            if (is_array($purchase->line_items) && $purchase->line_items !== []) {
                $productNames = Product::query()
                    ->whereIn('id', collect($purchase->line_items)->pluck('product_id')->filter()->unique())
                    ->pluck('name', 'id');

                $purchase->line_items = collect($purchase->line_items)
                    ->map(function (array $item) use ($productNames): array {
                        $item['product_name'] = $productNames[$item['product_id'] ?? null] ?? ($item['product_name'] ?? null);

                        return $item;
                    })
                    ->all();

                $purchase->total_amount = collect($purchase->line_items)->sum(function (array $item): float {
                    $quantity = max(0, (int) ($item['quantity'] ?? 0));
                    $unitCost = max(0, (float) ($item['unit_cost'] ?? 0));
                    $discount = max(0, (float) ($item['discount'] ?? 0));
                    $shipping = max(0, (float) ($item['shipping_cost'] ?? 0));

                    return max(0, ($quantity * $unitCost) - $discount + $shipping);
                });
            }

            if ((float) $purchase->total_amount <= 0 || (float) $purchase->paid_amount < 0 || (float) $purchase->paid_amount > (float) $purchase->total_amount) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'Paid amount must be between zero and the purchase total.',
                ]);
            }

            if ((float) $purchase->paid_amount > 0 && ! $purchase->payment_account_id) {
                throw ValidationException::withMessages([
                    'payment_account_id' => 'Select the fund account used for this payment.',
                ]);
            }
        });

        static::saved(fn (self $purchase) => $purchase->syncPaymentTransaction());
        static::deleted(fn (self $purchase) => $purchase->paymentTransaction()->delete());
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(AccountingSupplier::class, 'supplier_id');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(FundAccount::class, 'payment_account_id');
    }

    public function paymentTransaction(): MorphOne
    {
        return $this->morphOne(FundTransaction::class, 'source');
    }

    public function getDueAmountAttribute(): float
    {
        return max(0, (float) $this->total_amount - (float) $this->paid_amount);
    }

    private function syncPaymentTransaction(): void
    {
        if ((float) $this->paid_amount <= 0 || ! $this->payment_account_id) {
            $this->paymentTransaction()->delete();

            return;
        }

        $this->paymentTransaction()->updateOrCreate([], [
            'fund_account_id' => $this->payment_account_id,
            'direction' => 'out',
            'amount' => $this->paid_amount,
            'transaction_date' => $this->purchase_date,
            'reference_number' => $this->invoice_number ?: $this->purchase_number,
            'created_by' => $this->created_by,
            'description' => 'Purchase payment · '.$this->purchase_number,
        ]);
    }
}
