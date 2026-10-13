<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * @return array{coupon: Coupon, discount: float}
     */
    public function quote(
        string $code,
        float $subtotal,
        ?int $userId = null,
        ?string $customerPhone = null,
    ): array {
        $coupon = Coupon::query()
            ->where('code', $this->normalizeCode($code))
            ->first();

        $this->assertRedeemable($coupon, $subtotal, $this->customerKey($userId, $customerPhone));

        return [
            'coupon' => $coupon,
            'discount' => $this->discountFor($coupon, $subtotal),
        ];
    }

    /**
     * The caller must already be inside the order transaction. Locking the
     * coupon row prevents two checkouts from exceeding a limited campaign.
     *
     * @return array{coupon: Coupon|null, discount: float}
     */
    public function redeem(
        Order $order,
        float $subtotal,
        ?string $code,
        ?int $userId,
        ?string $customerPhone,
    ): array {
        if (blank($code)) {
            return ['coupon' => null, 'discount' => 0.0];
        }

        $coupon = Coupon::query()
            ->where('code', $this->normalizeCode($code))
            ->lockForUpdate()
            ->first();
        $customerKey = $this->customerKey($userId, $customerPhone);

        $this->assertRedeemable($coupon, $subtotal, $customerKey);

        $discount = $this->discountFor($coupon, $subtotal);
        CouponUsage::query()->create([
            'coupon_id' => $coupon->getKey(),
            'order_id' => $order->getKey(),
            'user_id' => $userId,
            'customer_key' => $customerKey,
            'discount_amount' => $discount,
            'used_at' => now(),
        ]);
        $coupon->increment('used_count');

        return ['coupon' => $coupon, 'discount' => $discount];
    }

    public function release(Order $order): void
    {
        $usage = CouponUsage::query()
            ->where('order_id', $order->getKey())
            ->lockForUpdate()
            ->first();

        if (! $usage) {
            return;
        }

        $coupon = Coupon::query()->withTrashed()->lockForUpdate()->find($usage->coupon_id);
        $usage->delete();

        if ($coupon && $coupon->used_count > 0) {
            $coupon->decrement('used_count');
        }
    }

    public function normalizeCode(string $code): string
    {
        return Str::upper(trim($code));
    }

    private function assertRedeemable(?Coupon $coupon, float $subtotal, ?string $customerKey): void
    {
        if (! $coupon || ! $coupon->is_active) {
            $this->fail(__('This coupon code is invalid or inactive.'));
        }

        if ($coupon->starts_at?->isFuture()) {
            $this->fail(__('This coupon is not active yet.'));
        }

        if ($coupon->expires_at?->isPast()) {
            $this->fail(__('This coupon has expired.'));
        }

        if ($subtotal < (float) $coupon->minimum_order_amount) {
            $this->fail(__('A minimum order of :amount is required for this coupon.', [
                'amount' => '৳'.number_format((float) $coupon->minimum_order_amount, 0),
            ]));
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            $this->fail(__('This coupon has reached its usage limit.'));
        }

        if ($coupon->usage_limit_per_customer !== null && $customerKey !== null) {
            $customerUses = CouponUsage::query()
                ->where('coupon_id', $coupon->getKey())
                ->where('customer_key', $customerKey)
                ->count();

            if ($customerUses >= $coupon->usage_limit_per_customer) {
                $this->fail(__('You have already used this coupon the maximum number of times.'));
            }
        }
    }

    private function discountFor(Coupon $coupon, float $subtotal): float
    {
        $discount = $coupon->discount_type === 'percentage'
            ? $subtotal * (min(100, (float) $coupon->discount_value) / 100)
            : (float) $coupon->discount_value;

        if ($coupon->maximum_discount_amount !== null) {
            $discount = min($discount, (float) $coupon->maximum_discount_amount);
        }

        return round(max(0, min($discount, $subtotal)), 2);
    }

    private function customerKey(?int $userId, ?string $customerPhone): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $customerPhone);
        $identity = $phone !== '' ? 'phone:'.$phone : ($userId ? 'user:'.$userId : null);

        return $identity ? hash('sha256', $identity) : null;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['coupon_code' => $message]);
    }
}
