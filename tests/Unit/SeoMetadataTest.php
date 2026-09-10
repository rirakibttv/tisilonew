<?php

namespace Tests\Unit;

use App\Support\SeoMetadata;
use PHPUnit\Framework\TestCase;

class SeoMetadataTest extends TestCase
{
    public function test_description_is_generated_from_the_first_available_content_source(): void
    {
        $description = SeoMetadata::description(
            null,
            '<p>First paragraph &amp; details.</p><p>Second paragraph.</p>',
            'Fallback content',
        );

        $this->assertSame('First paragraph & details. Second paragraph.', $description);
    }

    public function test_generated_description_is_plain_text_and_limited_to_160_characters(): void
    {
        $description = SeoMetadata::description(
            '<style>body { color: red; }</style><script>alert(1)</script><p>'.str_repeat('ক', 200).'</p>',
        );

        $this->assertSame(SeoMetadata::DESCRIPTION_LENGTH, mb_strlen($description));
        $this->assertStringNotContainsString('alert', $description);
        $this->assertStringNotContainsString('<p>', $description);
    }
}
