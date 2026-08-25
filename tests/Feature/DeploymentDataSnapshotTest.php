<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use App\Services\DeploymentDataSnapshot;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeploymentDataSnapshotTest extends TestCase
{
    use DatabaseTransactions;

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
        $user = User::query()->create([
            'name' => 'Deployment User '.$token,
            'email' => 'deployment-'.$token.'@example.test',
            'phone' => '01'.random_int(100000000, 999999999),
            'password' => 'DeploymentPassword!123',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tisilo-deployment-'.$token.'.json';

        try {
            $service = app(DeploymentDataSnapshot::class);
            $service->export($path);
            $snapshot = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            $keys = $this->recursiveKeys($snapshot);

            foreach (['password', 'remember_token', 'session_id', 'last_login_at'] as $forbidden) {
                $this->assertNotContains($forbidden, $keys);
            }

            $this->assertContains('purchase_price', $keys);
            $this->assertContains('stock_quantity', $keys);
            $this->assertContains('stock_status', $keys);
            $this->assertContains('password_hash', $keys);

            $userData = collect($snapshot['users'])->firstWhere('email', $user->email);
            $this->assertSame($user->phone, $userData['phone']);
            $this->assertSame('admin', $userData['role']);
            $this->assertTrue(Hash::check('DeploymentPassword!123', $userData['password_hash']));

            $product->update([
                'regular_price' => 1000,
                'purchase_price' => 700,
                'stock_quantity' => 7,
            ]);
            $variation->update([
                'purchase_price' => 725,
                'regular_price' => 1200,
                'stock_quantity' => 2,
                'stock_status' => 'out_of_stock',
            ]);
            $user->update([
                'name' => 'Changed User',
                'password' => 'ChangedPassword!123',
                'role' => 'customer',
                'status' => 'suspended',
            ]);
            $service->import($path);
            $product->refresh();
            $variation->refresh();
            $user->refresh();

            $this->assertSame('900.00', $product->regular_price);
            $this->assertSame('500.00', $product->purchase_price);
            $this->assertSame(42, $product->stock_quantity);
            $this->assertSame('550.00', $variation->purchase_price);
            $this->assertSame('950.00', $variation->regular_price);
            $this->assertSame(23, $variation->stock_quantity);
            $this->assertSame('in_stock', $variation->stock_status);
            $this->assertSame('Deployment User '.$token, $user->name);
            $this->assertSame('admin', $user->role->value);
            $this->assertSame('active', $user->status->value);
            $this->assertTrue(Hash::check('DeploymentPassword!123', $user->password));
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
