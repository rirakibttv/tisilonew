<?php

namespace App\Support;

use Illuminate\Support\Str;

final class SeoMetadata
{
    public const DESCRIPTION_LENGTH = 160;

    public static function title(?string $title, string $fallback = 'Tisilo'): string
    {
        return self::plainText($title) ?: self::plainText($fallback);
    }

    public static function description(mixed ...$sources): string
    {
        foreach ($sources as $source) {
            if (! is_string($source) && ! is_numeric($source)) {
                continue;
            }

            $summary = self::plainText((string) $source);

            if ($summary !== '') {
                return Str::limit($summary, self::DESCRIPTION_LENGTH, '');
            }
        }

        return '';
    }

    private static function plainText(?string $value): string
    {
        if (blank($value)) {
            return '';
        }

        $withoutExecutableContent = preg_replace(
            '/<(script|style)\b[^>]*>.*?<\/\1>/is',
            ' ',
            $value,
        ) ?? $value;
        $withBlockSpacing = preg_replace(
            ['/<\s*br\s*\/?\s*>/i', '/<\/\s*(p|div|li|h[1-6]|tr)\s*>/i'],
            ' ',
            $withoutExecutableContent,
        ) ?? $withoutExecutableContent;
        $decoded = html_entity_decode(strip_tags($withBlockSpacing), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $decoded));
    }
}
