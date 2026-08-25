<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'value' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function sourceLabel(): string
    {
        $source = strtolower(trim((string) $this->traffic_source));
        $clickSource = strtolower(trim((string) $this->click_source));
        $referrer = strtolower(trim((string) $this->referrer_host));

        if ($clickSource === 'facebook' || in_array($source, ['facebook', 'fb', 'instagram', 'meta', 'facebook_ads'], true)
            || str_contains($referrer, 'facebook.') || str_contains($referrer, 'instagram.') || str_contains($referrer, 'fb.com')) {
            return 'Facebook';
        }

        if ($clickSource === 'google' || in_array($source, ['google', 'google_ads', 'adwords'], true)
            || str_contains($referrer, 'google.') || str_contains($referrer, 'googleadservices.')) {
            return 'Google Search';
        }

        if ($source !== '') {
            return str($source)->replace(['_', '-'], ' ')->title()->toString();
        }

        $siteHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($referrer === '' || $referrer === $siteHost || in_array($referrer, ['localhost', '127.0.0.1'], true)) {
            return 'Direct';
        }

        return $referrer;
    }

    public function sourceType(): string
    {
        return match ($this->sourceLabel()) {
            'Facebook' => 'facebook',
            'Google Search' => 'google',
            'Direct' => 'direct',
            default => 'referral',
        };
    }
}
