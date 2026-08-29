<?php

namespace App\Services;

use App\Core\FileCache;

final class PublicCache
{
    public static function invalidate(array $config): void
    {
        try {
            FileCache::fromConfig($config)->clear();
        } catch (\Throwable) {
        }
    }
}
