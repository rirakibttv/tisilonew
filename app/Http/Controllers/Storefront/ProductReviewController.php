<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductReviewController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->status === 'published', 404);

        $customer = $request->user();
        abort_unless($customer?->role === UserRole::Customer, 403);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:150'],
            'review' => ['required', 'string', 'min:10', 'max:3000'],
        ], [
            'rating.required' => 'রেটিং নির্বাচন করুন।',
            'rating.between' => 'রেটিং ১ থেকে ৫-এর মধ্যে হতে হবে।',
            'review.required' => 'আপনার রিভিউ লিখুন।',
            'review.min' => 'রিভিউ অন্তত ১০ অক্ষরের হতে হবে।',
        ]);

        try {
            DB::transaction(function () use ($customer, $product, $validated): void {
                $orderItem = OrderItem::query()
                    ->where('product_id', $product->getKey())
                    ->whereHas('order', fn ($query) => $query
                        ->where('user_id', $customer->getKey())
                        ->where('status', OrderStatus::Delivered->value))
                    ->latest('id')
                    ->first();

                if (! $orderItem) {
                    throw ValidationException::withMessages([
                        'review' => 'শুধু এই পণ্যটি কিনে ডেলিভারি পাওয়া কাস্টমার রিভিউ দিতে পারবেন।',
                    ]);
                }

                $existingReview = ProductReview::query()
                    ->where('product_id', $product->getKey())
                    ->where('user_id', $customer->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($existingReview && $existingReview->status !== ProductReview::STATUS_REJECTED) {
                    throw ValidationException::withMessages([
                        'review' => $existingReview->status === ProductReview::STATUS_APPROVED
                            ? 'আপনার রিভিউটি ইতোমধ্যে প্রকাশিত হয়েছে।'
                            : 'আপনার রিভিউটি অনুমোদনের অপেক্ষায় আছে।',
                    ]);
                }

                $attributes = [
                    'order_item_id' => $orderItem->getKey(),
                    'reviewer_name' => $customer->name,
                    'reviewer_email' => $customer->email,
                    'rating' => (int) $validated['rating'],
                    'title' => $validated['title'] ?? null,
                    'review' => $validated['review'],
                    'status' => ProductReview::STATUS_PENDING,
                    'source' => ProductReview::SOURCE_CUSTOMER,
                    'is_verified_purchase' => true,
                ];

                if ($existingReview) {
                    $existingReview->fill($attributes)->save();

                    return;
                }

                $product->reviews()->create([
                    ...$attributes,
                    'user_id' => $customer->getKey(),
                ]);
            }, attempts: 3);
        } catch (QueryException $exception) {
            $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

            if (in_array($sqlState, ['23000', '23505'], true)) {
                throw ValidationException::withMessages([
                    'review' => 'এই পণ্যের জন্য আপনার একটি রিভিউ ইতোমধ্যে রয়েছে।',
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->to(route('store.products.show', $product).'#product-reviews')
            ->with('review_submitted', 'ধন্যবাদ। আপনার রিভিউ অনুমোদনের জন্য পাঠানো হয়েছে।');
    }
}
