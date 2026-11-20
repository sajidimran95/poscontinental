<?php

namespace App\Sync;

/**
 * Progress of the first full download, written by the background `sync:download`
 * process and read by the setup screen (storage/app/sync/download.json).
 */
class DownloadProgress
{
    /** No update for this long while "running" means the process died (app closed, crash). */
    const STALE_SECONDS = 600;

    public static function file(): string
    {
        return storage_path('app/sync/download.json');
    }

    public static function read(): ?array
    {
        $json = @file_get_contents(self::file());
        $data = $json ? json_decode($json, true) : null;
        if (! is_array($data)) {
            return null;
        }
        if ($data['state'] === 'running' && time() - (int) $data['updated_at'] > self::STALE_SECONDS) {
            $data['state'] = 'failed';
            $data['error'] = 'The download stopped (no progress for '.intdiv(self::STALE_SECONDS, 60).' minutes). Try again.';
        }

        return $data;
    }

    public static function start(): void
    {
        self::write(['state' => 'running', 'started_at' => time(), 'message' => 'Starting...', 'percent' => 0, 'log' => [], 'error' => null]);
    }

    public static function line(string $message, ?float $percent = null): void
    {
        $data = self::read() ?? ['state' => 'running', 'started_at' => time(), 'log' => [], 'error' => null];
        $data['message'] = $message;
        if ($percent !== null) {
            $data['percent'] = $percent;
        }
        // keep table headers and warnings, page counters only replace the current message
        if (! str_contains($message, ' / ')) {
            $data['log'][] = date('H:i:s').'  '.$message;
            $data['log'] = array_slice($data['log'], -200);
        }
        self::write($data);
    }

    public static function finish(int $rows): void
    {
        $data = self::read() ?? ['log' => []];
        self::write(['state' => 'done', 'rows' => $rows, 'percent' => 100, 'message' => "Done: {$rows} records downloaded."] + $data);
    }

    public static function fail(string $error): void
    {
        $data = self::read() ?? ['log' => []];
        $data['state'] = 'failed';
        $data['error'] = $error;
        self::write($data);
    }

    public static function clear(): void
    {
        @unlink(self::file());
    }

    protected static function write(array $data): void
    {
        $data['updated_at'] = time();
        @mkdir(dirname(self::file()), 0775, true);
        $tmp = self::file().'.tmp';
        file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        @rename($tmp, self::file()) || copy($tmp, self::file());
    }
}
