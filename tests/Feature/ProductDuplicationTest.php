<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductTag;
use App\Models\ProductVariation;
use App\Models\User;
use App\Services\ProductDuplicator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class ProductDuplicationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_product_is_duplicated_as_a_draft_with_catalog_relationships_and_variations(): void
    {
        $tag = ProductTag::query()->create([
            'name' => 'Duplicate Tag',
            'slug' => 'duplicate-tag',
        ]);
        $attribute = Attribute::query()->create([
            'name' => 'Duplicate Color',
            'slug' => 'duplicate-color',
        ]);
        $attributeValue = AttributeValue::query()->create([
            'attribute_id' => $attribute->id,
            'value' => 'Blue',
            'slug' => 'blue',
        ]);
        $product = Product::query()->create([
            'name' => 'Duplicator Test Product',
            'slug' => 'duplicator-test-product',
            'product_type' => 'variable',
            'sku' => 'DUPLICATOR-PARENT',
            'barcode' => 'DUPLICATOR-PARENT-BARCODE',
            'regular_price' => 1400,
            'sale_price' => 1050,
            'stock_quantity' => 4,
            'stock_status' => 'in_stock',
            'featured_image' => 'products/duplicator.jpg',
            'gallery_images' => ['products/duplicator-gallery.jpg'],
            'status' => 'published',
            'featured' => true,
        ]);
        $product->tags()->attach($tag);
        $product->attributes()->attach($attribute);
        $variation = ProductVariation::query()->create([
            'product_id' => $product->id,
            'sku' => 'DUPLICATOR-VARIATION',
            'barcode' => 'DUPLICATOR-VARIATION-BARCODE',
            'regular_price' => 1400,
            'sale_price' => 1050,
            'stock_quantity' => 4,
            'stock_status' => 'in_stock',
            'image' => 'products/variations/duplicator.jpg',
            'status' => true,
            'is_default' => true,
        ]);
        $variation->attributeValues()->attach($attributeValue);

        $duplicate = app(ProductDuplicator::class)->duplicate($product);

        $this->assertNotSame($product->id, $duplicate->id);
        $this->assertSame('Duplicator Test Product (Copy)', $duplicate->name);
        $this->assertSame('duplicator-test-product-copy', $duplicate->slug);
        $this->assertSame('DUPLICATOR-PARENT-COPY', $duplicate->sku);
        $this->assertSame('DUPLICATOR-PARENT-BARCODE-COPY', $duplicate->barcode);
        $this->assertSame('draft', $duplicate->status);
        $this->assertFalse($duplicate->featured);
        $this->assertSame($product->featured_image, $duplicate->featured_image);
        $this->assertSame($product->gallery_images, $duplicate->gallery_images);
        $this->assertEquals([$tag->id], $duplicate->tags->modelKeys());
        $this->assertEquals([$attribute->id], $duplicate->attributes->modelKeys());
        $this->assertCount(1, $duplicate->variations);
        $this->assertSame('DUPLICATOR-VARIATION-COPY', $duplicate->variations->first()->sku);
        $this->assertSame('DUPLICATOR-VARIATION-BARCODE-COPY', $duplicate->variations->first()->barcode);
        $this->assertEquals([$attributeValue->id], $duplicate->variations->first()->attributeValues->modelKeys());
    }

    public function test_all_products_table_places_working_actions_beside_the_product_image(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);
        $product = Product::query()->create([
            'name' => 'Action Column Product',
            'slug' => 'action-column-product',
            'product_type' => 'simple',
            'regular_price' => 500,
            'stock_quantity' => 3,
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);

        Livewire::actingAs($admin)
            ->test(ListProducts::class)
            ->assertSeeInOrder(['SL', 'Image', 'Action', 'Product'])
            ->assertSee('View')
            ->assertSee('Edit')
            ->assertSee('Duplicate')
            ->call('duplicateProduct', $product->id)
            ->assertHasNoErrors();

        $this->actingAs($admin)
            ->get('/admin/products/create')
            ->assertOk()
            ->assertSee('Basic Information')
            ->assertSee('Publishing')
            ->assertSee('Product Images')
            ->assertSee('Product Codes')
            ->assertSee('Shipping')
            ->assertDontSee('SEO Title')
            ->assertDontSee('Meta Description')
            ->assertDontSee('Meta Keywords');

        $this->assertDatabaseHas('products', [
            'name' => 'Action Column Product (Copy)',
            'slug' => 'action-column-product-copy',
            'status' => 'draft',
        ]);
    }

    public function test_variable_product_edit_keeps_the_existing_variation_editor(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);
        $product = Product::query()->create([
            'name' => 'Variable Layout Product',
            'slug' => 'variable-layout-product',
            'product_type' => 'variable',
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);

        $this->actingAs($admin)
            ->get('/admin/products/'.$product->getKey().'/edit')
            ->assertOk()
            ->assertSee('Basic Information')
            ->assertSee('Variation Attributes')
            ->assertSee('VariationsRelationManager', false);
    }

    public function test_product_edit_actions_replace_the_product_sort_order_field_in_publishing(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);
        $product = Product::query()->create([
            'name' => 'Publishing Action Product',
            'slug' => 'publishing-action-product',
            'product_type' => 'simple',
            'regular_price' => 500,
            'stock_quantity' => 3,
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);

        $this->actingAs($admin)
            ->get('/admin/products/'.$product->getKey().'/edit')
            ->assertOk()
            ->assertSee('Publishing')
            ->assertSee('Save Changes')
            ->assertSee('Cancel')
            ->assertDontSee('Sort Order');
    }

    public function test_all_products_keeps_new_uploads_on_top_with_their_permanent_serial_number(): void
    {
        Filament::setCurrentPanel('admin');
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);
        $olderProduct = Product::query()->create([
            'name' => 'Serial Order Product Older',
            'slug' => 'serial-order-product-older',
            'product_type' => 'simple',
            'regular_price' => 500,
            'stock_quantity' => 3,
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);
        $newerProduct = Product::query()->create([
            'name' => 'Serial Order Product Newer',
            'slug' => 'serial-order-product-newer',
            'product_type' => 'simple',
            'regular_price' => 500,
            'stock_quantity' => 3,
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);

        Livewire::actingAs($admin)
            ->test(ListProducts::class)
            ->searchTable('Serial Order Product')
            ->assertCanSeeTableRecords([$newerProduct, $olderProduct], inOrder: true)
            ->assertTableColumnStateSet('id', $olderProduct->id, $olderProduct)
            ->assertTableColumnStateSet('id', $newerProduct->id, $newerProduct);
    }
}
