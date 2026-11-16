<?php

namespace App\Sync\Desktop;

use App\Sync\SyncClient;
use App\Sync\SyncSchema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Start-up steps of the desktop (NativePHP) app.
 */
class DesktopRuntime
{
    /** Folder in the user's app data, kept across app updates. */
    public static function userDataPath(string $sub = ''): string
    {
        $base = env('NATIVEPHP_USER_DATA_PATH') ?: storage_path('app/desktop');
        $path = rtrim($base, '\\/').($sub !== '' ? DIRECTORY_SEPARATOR.$sub : '');
        if (! is_dir($path)) {
            @mkdir($path, 0775, true);
        }

        return $path;
    }

    public static function boot(): void
    {
        // only in the installed app, never in a developer checkout
        if (app()->environment('production')) {
            self::linkUploads();
        }
        DesktopDatabase::start();

        // a new PC gets its tables from the server in the setup screen;
        // after an app update only the new migrations run here
        if (app(SyncClient::class)->isBootstrapped()) {
            Artisan::call('migrate', ['--force' => true]);

            if (empty(SyncSchema::existingTriggers())) {
                SyncSchema::installTriggers();
            }
        }
    }

    /**
     * public/uploads is inside the app folder, which every update replaces.
     * Point it (directory junction) at a folder in app data, after copying in
     * any files shipped with the app.
     */
    public static function linkUploads(): void
    {
        $link = public_path('uploads');
        $target = self::userDataPath('uploads');

        if (is_link($link) || (is_dir($link) && realpath($link) === realpath($target))) {
            return;
        }

        if (is_dir($link)) {
            foreach (File::allFiles($link) as $file) {
                $dest = $target.DIRECTORY_SEPARATOR.$file->getRelativePathname();
                if (! is_file($dest)) {
                    File::ensureDirectoryExists(dirname($dest));
                    File::copy($file->getPathname(), $dest);
                }
            }
            File::deleteDirectory($link);
        }

        if (PHP_OS_FAMILY === 'Windows') {
            exec('cmd /c mklink /J "'.$link.'" "'.$target.'"');
        } else {
            @symlink($target, $link);
        }
    }
}
