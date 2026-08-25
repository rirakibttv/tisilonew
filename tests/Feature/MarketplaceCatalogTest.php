<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Enums\UserRole;
use App\Enums\VendorListingStatus;
use App\Enums\VendorStatus;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Models\VendorListingItem;
use App\Models\VendorWarehouse;
use App\Services\Inventory\InventoryService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketplaceCatalogTest extends TestCase
{
    use DatabaseTransactions;

    public function test_multiple_vendors_can_sell_the_same_catalog_product_independently(): void
    {
        $product = $this->createProduct();
        $firstVendor = $this->createVendor('First Vendor');
        $secondVendor = $this->createVendor('Second Vendor');

        $firstListing = $this->createListing($firstVendor, $product);
        $secondListing = $this->createListing($secondVendor, $product);

        $firstItem = $this->createItem($firstListing, 'SHARED-SKU', 1000);
        $secondItem = $this->createItem($secondListing, 'SHARED-SKU', 950);

        $this->assertNotSame($firstListing->id, $secondListing->id);
        $this->assertSame('1000.00', $firstItem->regular_price);
        $this->assertSame('950.00', $secondItem->regular_price);
    }

    public function test_inventory_is_vendor_scoped_and_movements_are_idempotent(): void
    {
        $product = $this->createProduct();
        $vendor = $this->createVendor('Inventory Vendor');
        $otherVendor = $this->createVendor('Other Vendor');
        $listing = $this->createListing($vendor, $product);
        $item = $this->createItem($listing, 'INV-SKU', 500);

        $warehouse = VendorWarehouse::query()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'address_line_1' => 'Dhaka',
            'district' => 'Dhaka',
            'is_default' => true,
            'status' => true,
        ]);

        $otherWarehouse = VendorWarehouse::query()->create([
            'vendor_id' => $otherVendor->id,
            'name' => 'Other Warehouse',
            'code' => 'OTHER',
            'address_line_1' => 'Chattogram',
            'district' => 'Chattogram',
            'is_default' => true,
            'status' => true,
        ]);

        try {
            InventoryStock::query()->create([
                'vendor_listing_item_id' => $item->id,
                'vendor_warehouse_id' => $otherWarehouse->id,
            ]);

            $this->fail('Cross-vendor inventory was accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('inventory_stocks', [
                'vendor_listing_item_id' => $item->id,
                'vendor_warehouse_id' => $otherWarehouse->id,
            ]);
        }

        $stock = InventoryStock::query()->create([
            'vendor_listing_item_id' => $item->id,
            'vendor_warehouse_id' => $warehouse->id,
        ]);

        $service = app(InventoryService::class);
        $key = 'opening-'.Str::uuid();

        $firstMovement = $service->apply(
            $stock,
            InventoryMovementType::Opening,
            quantityDelta: 10,
            idempotencyKey: $key,
        );

        $replayedMovement = $service->apply(
            $stock,
            InventoryMovementType::Opening,
            quantityDelta: 10,
            idempotencyKey: $key,
        );

        $service->apply(
            $stock,
            InventoryMovementType::Reservation,
            reservedDelta: 3,
        );

        $stock->refresh();

        $this->assertTrue($firstMovement->is($replayedMovement));
        $this->assertSame(10, $stock->quantity);
        $this->assertSame(3, $stock->reserved_quantity);
        $this->assertSame(7, $stock->available_quantity);

        $this->expectException(DomainException::class);

        $service->apply(
            $stock,
            InventoryMovementType::Reservation,
            reservedDelta: 8,
        );
    }

    private function createVendor(string $name): Vendor
    {
        $owner = User::factory()->create(['role' => UserRole::VendorOwner]);

        return Vendor::query()->create([
            'owner_id' => $owner->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'status' => VendorStatus::Active,
            'commission_rate' => 10,
        ]);
    }

    private function createProduct(): Product
    {
        return Product::query()->create([
            'name' => 'Catalog Product '.Str::random(6),
            'slug' => 'catalog-product-'.Str::lower(Str::random(8)),
            'product_type' => 'simple',
            'regular_price' => 0,
            'stock_quantity' => 0,
            'stock_status' => 'out_of_stock',
            'status' => 'published',
        ]);
    }

    private function createListing(Vendor $vendor, Product $product): VendorListing
    {
        return VendorListing::query()->create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => VendorListingStatus::Approved,
        ]);
    }

    private function createItem(
        VendorListing $listing,
        string $sellerSku,
        float $regularPrice,
    ): VendorListingItem {
        return VendorListingItem::query()->create([
            'vendor_listing_id' => $listing->id,
            'seller_sku' => $sellerSku,
            'regular_price' => $regularPrice,
            'status' => 'active',
            'is_default' => true,
        ]);
    }
}
