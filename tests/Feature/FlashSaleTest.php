<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\EditFlashSale;
use App\Filament\Resources\FlashSaleItemsRelationManager;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FlashSaleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    public function test_admin_can_manage_flash_sale_and_its_product_price_list(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);
        $sale = $this->flashSale('Admin Flash Sale');
        $product = $this->product('Admin Flash Product', 1200);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Flash Sale');

        $this->actingAs($admin)
            ->get('/admin/flash-sales/'.$sale->getKey().'/edit')
            ->assertOk()
            ->assertSee('Flash Sale Product &amp; Price List', false);

        Livewire::actingAs($admin)
            ->test(FlashSaleItemsRelationManager::class, [
                'ownerRecord' => $sale,
                'pageClass' => EditFlashSale::class,
            ])
            ->callTableAction('create', data: [
                'product_id' => $product->getKey(),
                'flash_price' => 850,
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('flash_sale_items', [
            'flash_sale_id' => $sale->getKey(),
            'product_id' => $product->getKey(),
            'flash_price' => 850,
        ]);
    }

    public function test_active_flash_sale_is_shown_above_promotional_slides_and_sets_cart_price(): void
    {
        $sale = $this->flashSale('Live Flash Sale');
        $product = $this->product('Live Flash Product', 1200);
        $sale->items()->create([
            'product_id' => $product->getKey(),
            'flash_price' => 799,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->get('/');
        $response->assertOk()
            ->assertSee('Flash Sale')
            ->assertSee('Live Flash Product')
            ->assertSee('৳799');

        $html = $response->getContent();
        $this->assertLessThan(
            strpos($html, 'সুপার ডিসকাউন্ট ডিল'),
            strpos($html, 'id="flash-sale"'),
        );

        $this->post(route('store.cart.store'), [
            'product_id' => $product->getKey(),
            'quantity' => 1,
        ])->assertRedirect(route('store.cart.index'))
            ->assertSessionHas('store_cart.catalog-'.$product->getKey().'-base.price', 799.0);
    }

    public function test_expired_flash_sale_price_is_not_used(): void
    {
        $product = $this->product('Expired Flash Product', 1200);
        $sale = FlashSale::query()->create([
            'name' => 'Expired Flash Sale',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            'is_active' => true,
        ]);
        $sale->items()->create([
            'product_id' => $product->getKey(),
            'flash_price' => 500,
            'is_active' => true,
        ]);

        $this->get('/')->assertOk()->assertDontSee('Expired Flash Product');

        $this->post(route('store.cart.store'), [
            'product_id' => $product->getKey(),
            'quantity' => 1,
        ])->assertSessionHas('store_cart.catalog-'.$product->getKey().'-base.price', 1200.0);
    }

    private function flashSale(string $name): FlashSale
    {
        return FlashSale::query()->create([
            'name' => $name.' '.Str::random(6),
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);
    }

    private function product(string $name, float $price): Product
    {
        $token = Str::lower(Str::random(8));

        return Product::query()->create([
            'name' => $name.' '.$token,
            'slug' => Str::slug($name).'-'.$token,
            'product_type' => 'simple',
            'regular_price' => $price,
            'manage_stock' => true,
            'stock_quantity' => 10,
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);
    }
}
