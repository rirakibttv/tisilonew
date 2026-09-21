<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerOrderRequest extends Model
{
    public const TYPE_RETURN = 'return';

    public const TYPE_CANCELLATION = 'cancellation';

    protected $fillable = [
        'user_id',
        'order_id',
        'type',
        'reason',
        'details',
        'status',
        'admin_note',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
            default => 'Pending',
        };
    }
}
