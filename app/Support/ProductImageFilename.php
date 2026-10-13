<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use InvalidArgumentException;

final class ProductImageFilename
{
    /** @var array<string, string> */
    private const EXTENSIONS_BY_MIME_TYPE = [
        'image/avif' => 'avif',
        'image/gif' => 'gif',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** @return array<int, string> */
    public static function acceptedMimeTypes(): array
    {
        return array_keys(self::EXTENSIONS_BY_MIME_TYPE);
    }

    public static function make(
        string $productName,
        string $mimeType,
        Filesystem $disk,
        string $directory,
    ): string {
        $extension = self::extensionForMimeType($mimeType);
        $baseName = self::slug($productName);
        $directory = trim($directory, '/');
        $fileName = "{$baseName}.{$extension}";
        $suffix = 1;

        while ($disk->exists("{$directory}/{$fileName}")) {
            $fileName = "{$baseName}-{$suffix}.{$extension}";
            $suffix++;
        }

        return $fileName;
    }

    private static function extensionForMimeType(string $mimeType): string
    {
        $mimeType = strtolower(trim(strtok($mimeType, ';') ?: $mimeType));

        return self::EXTENSIONS_BY_MIME_TYPE[$mimeType]
            ?? throw new InvalidArgumentException("Unsupported product image MIME type [{$mimeType}].");
    }

    private static function slug(string $productName): string
    {
        $slug = mb_strtolower(trim($productName));
        $slug = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'product-image';
        }

        while (strlen($slug) > 180) {
            $slug = mb_substr($slug, 0, -1);
        }

        return rtrim($slug, '-');
    }
}
