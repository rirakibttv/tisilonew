<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number',
        'user_id',
        'landing_page_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'status',
        'payment_status',
        'payment_method',
        'channel',
        'subtotal_amount',
        'discount_amount',
        'shipping_amount',
        'shipping_zone',
        'shipping_region_id',
        'shipping_partner_id',
        'tax_amount',
        'total_amount',
        'currency',
        'shipping_address',
        'shipping_breakdown',
        'billing_address',
        'marketing_attribution',
        'tracking_number',
        'notes',
        'placed_at',
        'confirmed_at',
        'fulfilled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'shipping_address' => 'array',
            'shipping_breakdown' => 'array',
            'billing_address' => 'array',
            'marketing_attribution' => 'array',
            'placed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            $order->order_number ??= self::nextOrderNumber();
            $order->placed_at ??= now();
        });
    }

    private static function nextOrderNumber(): string
    {
        do {
            $number = 'TIS-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (self::withTrashed()->where('order_number', $number)->exists());

        return $number;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::Pending->value);
    }

    public function scopeVendorOrders(Builder $query): Builder
    {
        return $query->whereHas('items', fn (Builder $items): Builder => $items->whereNotNull('vendor_id'));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }

    public function shippingRegion(): BelongsTo
    {
        return $this->belongsTo(ShippingRegion::class);
    }

    public function shippingPartner(): BelongsTo
    {
        return $this->belongsTo(ShippingPartner::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function incompleteOrders(): HasMany
    {
        return $this->hasMany(IncompleteOrder::class, 'converted_order_id');
    }
}
