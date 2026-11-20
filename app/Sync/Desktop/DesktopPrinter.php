<?php

namespace App\Sync\Desktop;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Desktop app: prints receipts straight to a chosen printer (no print dialog),
 * through NativePHP's Electron print API. Settings are per PC and never synced:
 * storage/app/sync/printer.json
 */
class DesktopPrinter
{
    /** Paper sizes for Electron's webContents.print (microns). "default" = printer driver setting. */
    const PAPER = [
        'default' => 'Printer default (set in Windows printing preferences)',
        '80mm' => '80 mm receipt roll',
        '58mm' => '58 mm receipt roll',
        'A4' => 'A4',
        'Letter' => 'Letter',
    ];

    public static function available(): bool
    {
        return config('sync.mode') === 'client' && (bool) config('nativephp-internal.running');
    }

    public static function file(): string
    {
        return storage_path('app/sync/printer.json');
    }

    public static function settings(): array
    {
        $json = @file_get_contents(self::file());
        $data = $json ? json_decode($json, true) : null;

        return (is_array($data) ? $data : []) + ['enabled' => false, 'printer' => '', 'paper' => 'default'];
    }

    public static function save(array $settings): void
    {
        @mkdir(dirname(self::file()), 0775, true);
        file_put_contents(self::file(), json_encode([
            'enabled' => ! empty($settings['enabled']),
            'printer' => (string) ($settings['printer'] ?? ''),
            'paper' => array_key_exists($settings['paper'] ?? '', self::PAPER) ? $settings['paper'] : 'default',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** True when receipts should skip the print dialog on this PC. */
    public static function directEnabled(): bool
    {
        $s = self::settings();

        return self::available() && $s['enabled'] && $s['printer'] !== '';
    }

    /** Printers installed in Windows: [['name' => ..., 'label' => ..., 'default' => bool]] */
    public static function printers(): array
    {
        $response = self::api(15)->get('system/printers');
        if (! $response->successful()) {
            throw new RuntimeException('Could not read the printer list (HTTP '.$response->status().').');
        }

        return collect($response->json('printers') ?? [])->map(fn ($p) => [
            'name' => (string) ($p['name'] ?? ''),
            'label' => (string) (($p['displayName'] ?? '') ?: ($p['name'] ?? '')),
            'default' => ! empty($p['isDefault']),
        ])->filter(fn ($p) => $p['name'] !== '')->values()->all();
    }

    /** Prints a complete HTML document on the saved printer. */
    public static function print(string $html, ?string $printer = null): void
    {
        $s = self::settings();
        $printer = $printer ?? $s['printer'];
        if ($printer === '') {
            throw new RuntimeException('No receipt printer selected.');
        }

        // NativePHP loads it as "data:text/html;charset=UTF-8,{html}" without encoding it,
        // a "#" (CSS colours) would cut the document off, so it is encoded here
        $encoded = rawurlencode($html);
        if (strlen($encoded) > 1900000) {
            throw new RuntimeException('Receipt is too large for direct printing.');
        }

        $settings = ['silent' => true, 'deviceName' => $printer, 'printBackground' => true];
        $size = self::pageSize($s['paper']);
        if ($size !== null) {
            $settings['pageSize'] = $size;
            $settings['margins'] = ['marginType' => 'none'];
        }

        $response = self::api(60)->post('system/print', [
            'html' => $encoded,
            'printer' => $printer,
            'settings' => $settings,
        ]);
        if (! $response->successful()) {
            throw new RuntimeException('The printer "'.$printer.'" did not accept the job. Check that it is on and connected.');
        }
    }

    protected static function pageSize(string $paper)
    {
        return match ($paper) {
            '80mm' => ['width' => 80000, 'height' => 297000],
            '58mm' => ['width' => 58000, 'height' => 297000],
            'A4', 'Letter' => $paper,
            default => null,
        };
    }

    /** NativePHP API client with a short timeout (its own client waits up to an hour). */
    protected static function api(int $timeout): PendingRequest
    {
        if (! self::available()) {
            throw new RuntimeException('Direct printing only works in the desktop app.');
        }

        return Http::asJson()
            ->baseUrl(config('nativephp-internal.api_url', ''))
            ->timeout($timeout)
            ->withHeaders(['X-NativePHP-Secret' => (string) config('nativephp-internal.secret')]);
    }
}
