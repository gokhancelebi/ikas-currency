<?php

namespace App\Lib\ShopifySync;

class SyncStorage
{
    public static function basePath(): string
    {
        $path = (string) config('shopify.storage_path', storage_path('app/shopify-sync'));

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    public static function path(string $relative): string
    {
        $full = self::basePath().'/'.ltrim($relative, '/');
        $dir = dirname($full);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $full;
    }

    public static function writeJson(string $relative, mixed $data): void
    {
        file_put_contents(
            self::path($relative),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public static function write(string $relative, string $contents): void
    {
        file_put_contents(self::path($relative), $contents);
    }
}
