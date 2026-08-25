<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\DeploymentDataSnapshot;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeploymentDataSnapshotTest extends TestCase
{
    use DatabaseTransactions;

    public function test_snapshot_excludes_sensitive_and_live_operational_data(): void
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
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tisilo-deployment-'.$token.'.json';

        try {
            $service = app(DeploymentDataSnapshot::class);
            $service->export($path);
            $snapshot = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
            $keys = $this->recursiveKeys($snapshot);

            foreach (['password', 'email', 'phone', 'purchase_price', 'stock_quantity', 'session_id'] as $forbidden) {
                $this->assertNotContains($forbidden, $keys);
            }

            $product->update([
                'regular_price' => 1000,
                'purchase_price' => 700,
                'stock_quantity' => 7,
            ]);
            $service->import($path);
            $product->refresh();

            $this->assertSame('900.00', $product->regular_price);
            $this->assertSame('700.00', $product->purchase_price);
            $this->assertSame(7, $product->stock_quantity);
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
