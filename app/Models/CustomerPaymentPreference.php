<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPaymentPreference extends Model
{
    protected $fillable = ['user_id', 'default_method'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
