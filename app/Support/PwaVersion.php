<?php

namespace App\Support;

/**
 * A short code that changes whenever the app's installable files change (the compiled CSS/JS from the
 * Vite build, the service worker itself, the offline page, the manifest or the icons).
 *
 * The page registers the service worker as /sw.js?v=<this code>. A new address makes the browser
 * install the new worker, which then clears the old shell cache. So every deploy updates phones by
 * itself — nobody has to remember to edit a version number.
 */
class PwaVersion
{
    private static ?string $hash = null;

    public static function hash(): string
    {
        if (self::$hash !== null) {
            return self::$hash;
        }

        $files = ['build/manifest.json', 'build/.vite/manifest.json', 'sw.js', 'offline.html', 'manifest.json'];

        foreach (glob(public_path('icons/*')) ?: [] as $icon) {
            $files[] = 'icons/'.basename($icon);
        }

        $parts = [];

        foreach ($files as $relative) {
            $path = public_path($relative);
            $parts[] = $relative.':'.(is_file($path) ? md5_file($path) : '-');
        }

        return self::$hash = substr(md5(implode('|', $parts)), 0, 10);
    }

    /** Forget the remembered value (used by tests that change files). */
    public static function flush(): void
    {
        self::$hash = null;
    }
}
