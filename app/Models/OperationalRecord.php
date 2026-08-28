<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperationalRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'module',
        'section',
        'title',
        'reference',
        'status',
        'amount',
        'contact',
        'scheduled_at',
        'notes',
        'payload',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'scheduled_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
