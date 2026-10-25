<?php

namespace App\Support\Install;

use Illuminate\Support\Facades\Storage;

/**
 * Whether the installer has finished on this host.
 *
 * The state is the sentinel file `installed.txt` on the default disk
 * (storage/app - SentinelGuardsTheInstallerTest pins where that lands).
 * It is read at request time, never while the route file loads: until
 * roadmap TODO 72 routes/web.php registered the `setup/*` group only while
 * the file was absent, so `route:cache` froze the install state into the
 * route table and every uncached request paid a filesystem check.
 */
final class InstallState
{
    public const SENTINEL = 'installed.txt';

    public static function isInstalled(): bool
    {
        return Storage::exists(self::SENTINEL);
    }

    public static function markInstalled(): void
    {
        Storage::put(self::SENTINEL, "The program installed successfully at ".date("Y-m-d H:i:s").".\n\nPlease DO NOT DELETE this file, unless you want to reinstall this program.");
    }
}
