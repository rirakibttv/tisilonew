<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestoreProductImageAliasesTest extends TestCase
{
    public function test_it_copies_missing_aliases_without_removing_original_uploads(): void
    {
        Storage::fake('public');
        config(['product_image_recovery.aliases' => [
            'products/featured/seo-name.jpg' => 'products/featured/original-upload.jpg',
        ]]);
        Storage::disk('public')->put('products/featured/original-upload.jpg', 'product-image-bytes');

        $this->artisan('media:restore-product-image-aliases')
            ->expectsOutputToContain('1 restored, 0 already present or unavailable')
            ->assertSuccessful();

        Storage::disk('public')->assertExists('products/featured/original-upload.jpg');
        Storage::disk('public')->assertExists('products/featured/seo-name.jpg');
        $this->assertSame(
            'product-image-bytes',
            Storage::disk('public')->get('products/featured/seo-name.jpg'),
        );
    }

    public function test_it_does_not_overwrite_an_existing_alias_or_create_one_without_a_source(): void
    {
        Storage::fake('public');
        config(['product_image_recovery.aliases' => [
            'products/featured/already-there.jpg' => 'products/featured/source.jpg',
            'products/featured/source-missing.jpg' => 'products/featured/not-uploaded.jpg',
        ]]);
        Storage::disk('public')->put('products/featured/source.jpg', 'source-bytes');
        Storage::disk('public')->put('products/featured/already-there.jpg', 'keep-this');

        $this->artisan('media:restore-product-image-aliases')
            ->expectsOutputToContain('0 restored, 2 already present or unavailable')
            ->assertSuccessful();

        $this->assertSame('keep-this', Storage::disk('public')->get('products/featured/already-there.jpg'));
        Storage::disk('public')->assertMissing('products/featured/source-missing.jpg');
    }
}
