<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\ProductReviews\Pages\CreateProductReview;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProductReviewWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_review_menu_has_create_pending_and_all_review_flows(): void
    {
        $admin = $this->user(UserRole::Admin);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(['Create Review', 'Pending Review', 'All Review']);

        $this->actingAs($admin)->get('/admin/product-reviews/create')->assertOk()->assertSee('Review Information');
        $this->actingAs($admin)->get('/admin/product-reviews/pending')->assertOk()->assertSee('Pending Reviews');
        $this->actingAs($admin)->get('/admin/product-reviews')->assertOk()->assertSee('All Reviews');
    }

    public function test_only_customer_with_a_delivered_purchase_can_submit_a_pending_review(): void
    {
        $customer = $this->user(UserRole::Customer);
        $product = $this->product();
        $order = $this->orderWithProduct($customer, $product, OrderStatus::Pending);

        $payload = [
            'rating' => 5,
            'title' => 'খুব ভালো পণ্য',
            'review' => 'পণ্যটির মান এবং প্যাকেজিং দুটোই খুব ভালো ছিল।',
        ];

        $this->actingAs($customer)
            ->post(route('store.products.reviews.store', $product), $payload)
            ->assertSessionHasErrors('review');

        $this->assertDatabaseMissing('product_reviews', [
            'product_id' => $product->getKey(),
            'user_id' => $customer->getKey(),
        ]);

        $order->update(['status' => OrderStatus::Delivered]);

        $this->actingAs($customer)
            ->post(route('store.products.reviews.store', $product), $payload)
            ->assertRedirect(route('store.products.show', $product).'#product-reviews')
            ->assertSessionHas('review_submitted');

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->getKey(),
            'user_id' => $customer->getKey(),
            'order_item_id' => $order->items()->sole()->getKey(),
            'status' => ProductReview::STATUS_PENDING,
            'source' => ProductReview::SOURCE_CUSTOMER,
            'is_verified_purchase' => true,
        ]);

        $this->get(route('store.products.show', $product))
            ->assertOk()
            ->assertSee('আপনার রিভিউটি এখন অনুমোদনের অপেক্ষায় আছে।')
            ->assertDontSee($payload['review']);
    }

    public function test_approved_reviews_are_public_and_customers_cannot_submit_duplicates(): void
    {
        $customer = $this->user(UserRole::Customer);
        $product = $this->product();
        $this->orderWithProduct($customer, $product, OrderStatus::Delivered);
        $reviewText = 'এই যাচাইকৃত রিভিউটি অনুমোদনের পরে পণ্যের পাতায় দেখা যাবে।';

        $this->actingAs($customer)->post(route('store.products.reviews.store', $product), [
            'rating' => 4,
            'title' => 'সন্তুষ্ট',
            'review' => $reviewText,
        ])->assertRedirect();

        $review = ProductReview::query()->where('user_id', $customer->getKey())->sole();
        $review->update(['status' => ProductReview::STATUS_APPROVED]);

        $this->get(route('store.products.show', $product))
            ->assertOk()
            ->assertSee($reviewText)
            ->assertSee('যাচাইকৃত ক্রয়')
            ->assertSee('4.0');

        $this->actingAs($customer)
            ->post(route('store.products.reviews.store', $product), [
                'rating' => 1,
                'review' => 'একই পণ্যের জন্য আরেকটি রিভিউ জমা দেওয়া যাবে না।',
            ])
            ->assertSessionHasErrors('review');

        $this->assertSame(1, ProductReview::query()->where('user_id', $customer->getKey())->count());
    }

    public function test_admin_can_create_a_manual_review_but_vendor_has_no_creation_access(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = $this->user(UserRole::Admin);
        $vendor = $this->user(UserRole::VendorOwner);
        $product = $this->product();

        Livewire::actingAs($admin)
            ->test(CreateProductReview::class)
            ->fillForm([
                'product_id' => $product->getKey(),
                'reviewer_name' => 'Manual Reviewer',
                'reviewer_email' => 'manual@example.test',
                'rating' => 5,
                'title' => 'Admin review',
                'review' => 'This review was created and approved from the admin panel.',
                'status' => ProductReview::STATUS_APPROVED,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('product_reviews', [
            'product_id' => $product->getKey(),
            'created_by' => $admin->getKey(),
            'user_id' => null,
            'source' => ProductReview::SOURCE_ADMIN,
            'status' => ProductReview::STATUS_APPROVED,
            'is_verified_purchase' => false,
        ]);

        $this->assertFalse(Route::has('filament.seller.resources.product-reviews.create'));

        $this->actingAs($vendor)
            ->post(route('store.products.reviews.store', $product), [
                'rating' => 5,
                'review' => 'A vendor must never be able to create a product review.',
            ])
            ->assertForbidden();
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => UserStatus::Active,
        ]);
    }

    private function product(): Product
    {
        $suffix = Str::lower(Str::random(10));

        return Product::query()->create([
            'name' => 'Review Product '.$suffix,
            'slug' => 'review-product-'.$suffix,
            'product_type' => 'simple',
            'regular_price' => 1200,
            'stock_quantity' => 5,
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);
    }

    private function orderWithProduct(User $customer, Product $product, OrderStatus $status): Order
    {
        $order = Order::query()->create([
            'user_id' => $customer->getKey(),
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'status' => $status,
            'payment_status' => PaymentStatus::Unpaid,
            'subtotal_amount' => 1200,
            'total_amount' => 1200,
        ]);

        $order->items()->create([
            'product_id' => $product->getKey(),
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 1200,
            'total_amount' => 1200,
        ]);

        return $order;
    }
}
