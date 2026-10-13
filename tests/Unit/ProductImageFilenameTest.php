<?php

namespace Tests\Unit;

use App\Support\ProductImageFilename;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class ProductImageFilenameTest extends TestCase
{
    public function test_it_builds_a_public_filename_from_only_the_product_name(): void
    {
        Storage::fake('public');

        $fileName = ProductImageFilename::make(
            'Premium Quality Twill Bed Sheet PT-053',
            'image/jpeg',
            Storage::disk('public'),
            'products/featured',
        );

        $this->assertSame('premium-quality-twill-bed-sheet-pt-053.jpg', $fileName);
    }

    public function test_it_adds_wordpress_style_suffixes_for_duplicate_names(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/gallery/premium-bed-sheet.jpg', 'first');
        Storage::disk('public')->put('products/gallery/premium-bed-sheet-1.jpg', 'second');

        $fileName = ProductImageFilename::make(
            'Premium Bed Sheet',
            'image/jpeg',
            Storage::disk('public'),
            'products/gallery',
        );

        $this->assertSame('premium-bed-sheet-2.jpg', $fileName);
    }

    public function test_it_preserves_bengali_product_words_in_the_filename(): void
    {
        Storage::fake('public');

        $fileName = ProductImageFilename::make(
            'প্রিমিয়াম বেড শিট PT-053',
            'image/webp',
            Storage::disk('public'),
            'products/featured',
        );

        $this->assertSame('প্রিমিয়াম-বেড-শিট-pt-053.webp', $fileName);
    }

    public function test_it_rejects_unsafe_or_unsupported_image_extensions(): void
    {
        Storage::fake('public');

        $this->expectException(InvalidArgumentException::class);

        ProductImageFilename::make(
            'Product Name',
            'application/x-php',
            Storage::disk('public'),
            'products/featured',
        );
    }
}
