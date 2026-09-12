<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductReview extends Model
{
    protected $fillable = [
        'uuid',
        'product_id',
        'user_id',
        'order_item_id',
        'reviewer_name',
        'reviewer_email',
        'rating',
        'title',
        'review',
        'status',
        'is_verified_purchase',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_verified_purchase' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductReview $review): void {
            $review->uuid ??= (string) Str::uuid();
        });

        static::saving(function (ProductReview $review): void {
            $review->published_at = $review->status === 'approved'
                ? ($review->published_at ?? now())
                : null;
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'approved')
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
