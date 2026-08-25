<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryMovement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'inventory_stock_id',
        'performed_by',
        'type',
        'quantity_delta',
        'reserved_delta',
        'quantity_before',
        'quantity_after',
        'reserved_before',
        'reserved_after',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'reason',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => InventoryMovementType::class,
            'quantity_delta' => 'integer',
            'reserved_delta' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'reserved_before' => 'integer',
            'reserved_after' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(InventoryStock::class, 'inventory_stock_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
