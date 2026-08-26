<?php

namespace App\Models;

use App\Enums\VendorStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_id',
        'approved_by',
        'name',
        'slug',
        'legal_name',
        'email',
        'phone',
        'website',
        'logo',
        'banner',
        'description',
        'status',
        'commission_rate',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorStatus::class,
            'commission_rate' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'vendor_user')
            ->withPivot(['role', 'status', 'permissions', 'joined_at'])
            ->withTimestamps();
    }

    public function listings(): HasMany
    {
        return $this->hasMany(VendorListing::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(VendorWarehouse::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
