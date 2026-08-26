<?php

namespace App\Models;

use App\Enums\IncompleteOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncompleteOrder extends Model
{
    protected $fillable = [
        'user_id',
        'converted_order_id',
        'session_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_address',
        'items',
        'total_amount',
        'currency',
        'status',
        'notes',
        'last_activity_at',
        'recovered_at',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'total_amount' => 'decimal:2',
            'status' => IncompleteOrderStatus::class,
            'last_activity_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }
}
