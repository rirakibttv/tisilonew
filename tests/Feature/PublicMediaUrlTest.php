<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicMediaUrlTest extends TestCase
{
    public function test_public_media_url_is_request_host_relative_by_default(): void
    {
        $this->assertSame('/storage', config('filesystems.disks.public.url'));
        $this->assertSame(
            '/storage/products/featured/example.jpg',
            Storage::disk('public')->url('products/featured/example.jpg'),
        );
    }
}
