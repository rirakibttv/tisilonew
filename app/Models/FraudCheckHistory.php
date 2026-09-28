<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudCheckHistory extends Model
{
    protected $guarded = [];

    protected $hidden = [
        'mobile',
        'mobile_hash',
        'response_payload',
    ];

    protected function casts(): array
    {
        return [
            'mobile' => 'encrypted',
            'response_payload' => 'array',
            'success_ratio' => 'decimal:2',
        ];
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
