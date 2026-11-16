<?php

namespace App\Sync;

/**
 * Identity of this installation in the sync network.
 *
 * Server: node 1, settings from config/sync.php.
 * Desktop: node 2..id_step, settings saved by the setup screen in storage/app/sync/node.json
 * (kept out of .env because the bundled desktop .env is read-only and shared by every PC).
 */
class SyncNode
{
    protected static $settings = false;

    public static function mode(): string
    {
        return (string) config('sync.mode', 'off');
    }

    public static function isServer(): bool
    {
        return self::mode() === 'server';
    }

    public static function isClient(): bool
    {
        return self::mode() === 'client';
    }

    public static function file(): string
    {
        return storage_path('app/sync/node.json');
    }

    /**
     * Desktop settings: server_url, token, node_no, id_step, invoice_prefix, name
     */
    public static function settings(): ?array
    {
        if (self::$settings === false) {
            self::$settings = null;
            if (is_file(self::file())) {
                $data = json_decode((string) file_get_contents(self::file()), true);
                self::$settings = is_array($data) ? $data : null;
            }
        }

        return self::$settings;
    }

    public static function save(array $settings): void
    {
        if (! is_dir(dirname(self::file()))) {
            mkdir(dirname(self::file()), 0775, true);
        }
        file_put_contents(self::file(), json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        self::$settings = $settings;
    }

    public static function isConfigured(): bool
    {
        if (self::isServer()) {
            return true;
        }

        return self::isClient() && ! empty(self::settings()['token']) && ! empty(self::settings()['node_no']);
    }

    public static function nodeNo(): ?int
    {
        if (self::isServer()) {
            return (int) config('sync.server_node_no', 1);
        }
        if (self::isClient() && ! empty(self::settings()['node_no'])) {
            return (int) self::settings()['node_no'];
        }

        return null;
    }

    public static function idStep(): int
    {
        if (self::isClient() && ! empty(self::settings()['id_step'])) {
            return (int) self::settings()['id_step'];
        }

        return (int) config('sync.id_step', 100);
    }

    /**
     * Prefix for invoice / reference numbers made on this desktop, so two PCs never
     * produce the same number. Empty on the server.
     */
    public static function invoicePrefix(): string
    {
        if (self::isClient() && ! empty(self::settings()['invoice_prefix'])) {
            return self::settings()['invoice_prefix'].'-';
        }

        return '';
    }

    /**
     * SQL run on every new MySQL connection: puts this node on its own auto-increment lane.
     */
    public static function connectionInitSql(): ?string
    {
        $node_no = self::nodeNo();
        if (empty($node_no)) {
            return null;
        }
        $step = self::idStep();

        return 'SET SESSION auto_increment_increment = '.$step.', auto_increment_offset = '.$node_no;
    }
}
