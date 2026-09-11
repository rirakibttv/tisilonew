<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Models\VendorListingItem;
use App\Models\VendorWarehouse;
use App\Services\DeploymentDataSnapshot;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeploymentDataSnapshotTest extends TestCase
{
    use DatabaseTransactions;

    public function test_facebook_capi_snapshot_updates_events_without_overwriting_production_connection(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tisilo-facebook-capi-'.Str::random(10).'.json';
        $service = app(DeploymentDataSnapshot::class);

        try {
            SiteSetting::put('facebook_capi', [
                'enabled' => false,
                'pixel_id' => '111111111',
                'api_version' => 'v22.0',
                'events' => ['Purchase', 'OrderCancelled'],
            ], ['access_token' => 'local-token']);

            $service->export($path);
            $snapshot = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            $facebook = collect($snapshot['site_settings'])->firstWhere('key', 'facebook_capi');

            $this->assertSame(['events' => ['Purchase', 'OrderCancelled']], $facebook['values']);

            SiteSetting::put('facebook_capi', [
                'enabled' => true,
                'pixel_id' => '999999999',
                'api_version' => 'v23.0',
                'test_event_code' => 'TEST999',
                'events' => ['PageView'],
            ], ['access_token' => 'production-token']);

            $service->import($path);

            $values = SiteSetting::valuesFor('facebook_capi');
            $this->assertTrue($values['enabled']);
            $this->assertSame('999999999', $values['pixel_id']);
            $this->assertSame('v23.0', $values['api_version']);
            $this->assertSame('TEST999', $values['test_event_code']);
            $this->assertSame(['Purchase', 'OrderCancelled'], $values['events']);
            $this->assertSame('production-token', SiteSetting::secretsFor('facebook_capi')['access_token']);
        } finally {
            File::delete($path);
        }
    }

    public function test_snapshot_includes_complete_catalog_and_user_data_without_session_secrets(): void
    {
        $token = Str::lower(Str::random(10));
        $product = Product::query()->create([
            'name' => 'Deployment Product '.$token,
            'slug' => 'deployment-product-'.$token,
            'product_type' => 'simple',
            'sku' => 'DEPLOY-'.$token,
            'purchase_price' => 500,
            'regular_price' => 900,
            'sale_price' => 850,
            'stock_quantity' => 42,
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);
        $variation = ProductVariation::query()->create([
            'product_id' => $product->id,
            'sku' => 'DEPLOY-VARIATION-'.$token,
            'purchase_price' => 550,
            'regular_price' => 950,
            'sale_price' => 875,
            'stock_quantity' => 23,
            'low_stock_threshold' => 4,
            'stock_status' => 'in_stock',
            'status' => true,
            'is_default' => true,
        ]);
        $skuLessVariation = ProductVariation::query()->create([
            'product_id' => $product->id,
            'sku' => null,
            'purchase_price' => 600,
            'regular_price' => 1100,
            'sale_price' => 999,
            'stock_quantity' => 17,
            'stock_status' => 'in_stock',
            'status' => true,
            'is_default' => false,
            'sort_order' => 2,
        ]);
        $conflictingSkuVariation = ProductVariation::query()->create([
            'product_id' => $product->id,
            'sku' => 'DEPLOY-CONFLICT-'.$token,
            'purchase_price' => 650,
            'regular_price' => 1050,
            'sale_price' => 925,
            'stock_quantity' => 11,
            'stock_status' => 'in_stock',
            'status' => true,
            'is_default' => false,
            'sort_order' => 3,
        ]);
        $user = User::query()->create([
            'name' => 'Deployment User '.$token,
            'email' => 'deployment-'.$token.'@example.test',
            'phone' => '01'.random_int(100000000, 999999999),
            'password' => 'DeploymentPassword!123',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $userPhone = $user->phone;
        $setting = SiteSetting::put('deployment-'.$token, [
            'site_name' => 'Deployment Store',
        ], [
            'private_api_key' => 'never-export-this-secret-'.$token,
        ]);
        $vendor = Vendor::query()->create([
            'owner_id' => $user->id,
            'name' => 'Deployment Vendor '.$token,
            'slug' => 'deployment-vendor-'.$token,
            'email' => 'vendor-'.$token.'@example.test',
            'status' => 'active',
            'commission_rate' => 8.5,
        ]);
        $warehouse = VendorWarehouse::query()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Main Warehouse',
            'code' => 'MAIN-'.$token,
            'address_line_1' => 'Deployment Address',
            'district' => 'Dhaka',
            'is_default' => true,
            'status' => true,
        ]);
        $listing = VendorListing::query()->create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => 'approved',
            'condition' => 'new',
            'fulfillment_type' => 'vendor',
            'min_order_quantity' => 1,
            'handling_time_days' => 1,
        ]);
        $listingItem = VendorListingItem::query()->create([
            'vendor_listing_id' => $listing->id,
            'product_variation_id' => $variation->id,
            'seller_sku' => 'SELLER-'.$token,
            'regular_price' => 990,
            'status' => 'active',
            'is_default' => true,
        ]);
        $stock = InventoryStock::query()->create([
            'vendor_listing_item_id' => $listingItem->id,
            'vendor_warehouse_id' => $warehouse->id,
            'quantity' => 31,
            'reserved_quantity' => 3,
            'incoming_quantity' => 5,
            'reorder_point' => 6,
        ]);
        $user->wishlistProducts()->attach($product->id);
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tisilo-deployment-'.$token.'.json';

        try {
            $service = app(DeploymentDataSnapshot::class);
            $service->export($path);
            $snapshot = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            $keys = $this->recursiveKeys($snapshot);

            foreach (['password', 'password_hash', 'remember_token', 'session_id', 'last_login_at'] as $forbidden) {
                $this->assertNotContains($forbidden, $keys);
            }

            $this->assertContains('purchase_price', $keys);
            $this->assertContains('stock_quantity', $keys);
            $this->assertContains('stock_status', $keys);

            $userData = collect($snapshot['users'])->firstWhere('email', $user->email);
            $this->assertArrayNotHasKey('phone', $userData);
            $this->assertArrayNotHasKey('password_hash', $userData);
            $this->assertSame('admin', $userData['role']);
            $settingData = collect($snapshot['site_settings'])->firstWhere('key', $setting->key);
            $this->assertSame('Deployment Store', $settingData['values']['site_name']);
            $this->assertStringNotContainsString('never-export-this-secret-'.$token, File::get($path));
            $vendorData = collect($snapshot['vendors'])->firstWhere('slug', $vendor->slug);
            $this->assertSame($user->email, $vendorData['owner_email']);
            $this->assertSame('MAIN-'.$token, $vendorData['warehouses'][0]['code']);
            $this->assertSame('SELLER-'.$token, $vendorData['listings'][0]['items'][0]['seller_sku']);
            $this->assertSame(31, $vendorData['listings'][0]['items'][0]['stocks'][0]['quantity']);
            $productData = collect($snapshot['products'])->firstWhere('slug', $product->slug);
            $this->assertCount(3, $productData['variations']);
            $this->assertSame('999.00', collect($productData['variations'])->firstWhere('sku', null)['sale_price']);
            $this->assertTrue(collect($snapshot['wishlists'])->contains(
                fn (array $item): bool => $item['user_email'] === $user->email
                    && $item['product_slug'] === $product->slug,
            ));

            $product->update([
                'regular_price' => 1000,
                'purchase_price' => 700,
                'stock_quantity' => 7,
            ]);
            $conflictingProduct = Product::query()->create([
                'name' => 'Conflicting Deployment Product '.$token,
                'slug' => 'conflicting-deployment-product-'.$token,
                'product_type' => 'simple',
                'sku' => 'CONFLICT-'.$token,
                'regular_price' => 100,
                'stock_quantity' => 1,
                'stock_status' => 'in_stock',
                'status' => 'published',
            ]);
            $conflictingSkuVariation->update([
                'product_id' => $conflictingProduct->id,
                'regular_price' => 1400,
                'stock_quantity' => 2,
            ]);
            $variation->update([
                'purchase_price' => 725,
                'regular_price' => 1200,
                'stock_quantity' => 2,
                'stock_status' => 'out_of_stock',
            ]);
            $skuLessVariation->update([
                'sale_price' => 1300,
                'stock_quantity' => 1,
            ]);
            $user->update([
                'name' => 'Changed User',
                'password' => 'ChangedPassword!123',
                'role' => 'customer',
                'status' => 'suspended',
            ]);
            SiteSetting::put($setting->key, ['site_name' => 'Changed Store'], ['private_api_key' => 'preserve-me']);
            $vendor->update(['name' => 'Changed Vendor']);
            $warehouse->update(['address_line_1' => 'Changed Address']);
            $listingItem->update(['regular_price' => 1200]);
            $stock->update(['quantity' => 2]);
            $user->wishlistProducts()->detach($product->id);
            $service->import($path);
            $product->refresh();
            $variation->refresh();
            $skuLessVariation->refresh();
            $conflictingSkuVariation->refresh();
            $user->refresh();

            $this->assertSame('900.00', $product->regular_price);
            $this->assertSame('500.00', $product->purchase_price);
            $this->assertSame(42, $product->stock_quantity);
            $this->assertSame('550.00', $variation->purchase_price);
            $this->assertSame($conflictingProduct->id, $conflictingSkuVariation->product_id);
            $this->assertSame('1050.00', $conflictingSkuVariation->regular_price);
            $this->assertSame('950.00', $variation->regular_price);
            $this->assertSame(23, $variation->stock_quantity);
            $this->assertSame('in_stock', $variation->stock_status);
            $this->assertSame('999.00', $skuLessVariation->sale_price);
            $this->assertSame(17, $skuLessVariation->stock_quantity);
            $this->assertSame('Deployment User '.$token, $user->name);
            $this->assertSame('admin', $user->role->value);
            $this->assertSame('active', $user->status->value);
            $this->assertTrue(Hash::check('ChangedPassword!123', $user->password));
            $this->assertSame($userPhone, $user->phone);
            $setting->refresh();
            $this->assertSame('Deployment Store', $setting->values['site_name']);
            $this->assertSame('preserve-me', $setting->secret_values['private_api_key']);
            $this->assertSame('Deployment Vendor '.$token, $vendor->fresh()->name);
            $this->assertSame('Deployment Address', $warehouse->fresh()->address_line_1);
            $this->assertSame('990.00', $listingItem->fresh()->regular_price);
            $this->assertSame(31, $stock->fresh()->quantity);
            $this->assertDatabaseHas('wishlists', [
                'user_id' => $user->id,
                'product_id' => $product->id,
            ]);
        } finally {
            File::delete($path);
        }
    }

    public function test_snapshot_preserves_duplicate_subcategory_slugs_by_hierarchical_path(): void
    {
        $token = Str::lower(Str::random(10));
        $firstParent = Category::query()->create([
            'name' => 'Snapshot Men '.$token,
            'slug' => 'snapshot-men-'.$token,
            'status' => true,
        ]);
        $secondParent = Category::query()->create([
            'name' => 'Snapshot Women '.$token,
            'slug' => 'snapshot-women-'.$token,
            'status' => true,
        ]);
        $sharedSlug = 'inner-wear-'.$token;
        $firstChild = $firstParent->children()->create([
            'name' => 'Men Inner Wear',
            'slug' => $sharedSlug,
            'status' => true,
        ]);
        $secondChild = $secondParent->children()->create([
            'name' => 'Women Inner Wear',
            'slug' => $sharedSlug,
            'status' => true,
        ]);
        $firstProduct = Product::query()->create([
            'category_id' => $firstChild->id,
            'name' => 'Snapshot Men Product '.$token,
            'slug' => 'snapshot-men-product-'.$token,
            'product_type' => 'simple',
            'regular_price' => 500,
            'stock_quantity' => 1,
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);
        $secondProduct = Product::query()->create([
            'category_id' => $secondChild->id,
            'name' => 'Snapshot Women Product '.$token,
            'slug' => 'snapshot-women-product-'.$token,
            'product_type' => 'simple',
            'regular_price' => 600,
            'stock_quantity' => 1,
            'stock_status' => 'in_stock',
            'status' => 'published',
        ]);
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tisilo-category-path-'.$token.'.json';

        try {
            $service = app(DeploymentDataSnapshot::class);
            $service->export($path);
            $snapshot = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

            $this->assertTrue(collect($snapshot['categories'])->contains(
                fn (array $category): bool => $category['path'] === $firstParent->slug.'/'.$sharedSlug,
            ));
            $this->assertTrue(collect($snapshot['categories'])->contains(
                fn (array $category): bool => $category['path'] === $secondParent->slug.'/'.$sharedSlug,
            ));

            $firstProduct->update(['category_id' => $secondChild->id]);
            $secondProduct->update(['category_id' => $firstChild->id]);
            $service->import($path);

            $this->assertSame($firstChild->id, $firstProduct->fresh()->category_id);
            $this->assertSame($secondChild->id, $secondProduct->fresh()->category_id);
        } finally {
            File::delete($path);
        }
    }

    /** @return array<int, string> */
    private function recursiveKeys(array $data): array
    {
        $keys = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $keys[] = $key;
            }
            if (is_array($value)) {
                array_push($keys, ...$this->recursiveKeys($value));
            }
        }

        return array_values(array_unique($keys));
    }
}
