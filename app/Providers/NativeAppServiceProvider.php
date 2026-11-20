<?php

namespace App\Providers;

use App\Sync\Desktop\DesktopRuntime;
use App\Sync\SyncClient;
use App\Sync\SyncNode;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\Window;

/**
 * Desktop app (NativePHP) start-up. Only used inside the desktop build.
 */
class NativeAppServiceProvider implements ProvidesPhpIni
{
    public function boot(): void
    {
        $error = null;
        try {
            DesktopRuntime::boot();
        } catch (\Throwable $e) {
            report($e);
            $error = $e->getMessage();
        }

        Menu::create(
            Menu::app(),
            Menu::make(
                Menu::route('desktop.status', 'Sync status'),
                Menu::route('desktop.printer', 'Receipt printer'),
                Menu::separator(),
                Menu::reload(),
                Menu::fullscreen(),
            )->label('Sync'),
            Menu::edit(),
            Menu::view(),
            Menu::window(),
        );

        $ready = $error === null && SyncNode::isConfigured() && app(SyncClient::class)->isBootstrapped();

        Window::open()
            ->title(config('app.name', 'POS'))
            ->url(url($ready ? '/login' : '/desktop/setup'))
            ->width(1366)
            ->height(800)
            ->minWidth(1024)
            ->minHeight(640)
            ->maximized()
            ->rememberState();

        if ($error !== null) {
            logger()->error('Desktop start-up failed: '.$error);
        }
    }

    public function phpIni(): array
    {
        return [
            'memory_limit' => '1024M',
            'max_execution_time' => '0',
            'upload_max_filesize' => '50M',
            'post_max_size' => '60M',
        ];
    }
}
