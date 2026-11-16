<?php

namespace App\Sync\Desktop;

use Native\Desktop\Facades\ChildProcess;
use PDO;
use RuntimeException;

/**
 * The MySQL server bundled with the desktop app (extras/mysql, see scripts/desktop/prepare-mysql.ps1).
 * Data lives in the user's app-data folder so it survives app updates.
 */
class DesktopDatabase
{
    public static function baseDir(): string
    {
        $extras = env('NATIVEPHP_EXTRAS_PATH') ?: base_path('extras');

        return rtrim($extras, '\\/').DIRECTORY_SEPARATOR.'mysql';
    }

    public static function mysqld(): string
    {
        return self::baseDir().DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'mysqld.exe';
    }

    public static function dataDir(): string
    {
        return DesktopRuntime::userDataPath('mysql-data');
    }

    public static function port(): int
    {
        return (int) config('database.connections.mysql.port', 3307);
    }

    protected static function connect(string $user, string $password, ?string $database = null): PDO
    {
        $dsn = 'mysql:host=127.0.0.1;port='.self::port().($database ? ';dbname='.$database : '');

        return new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
    }

    public static function isRunning(): bool
    {
        $socket = @fsockopen('127.0.0.1', self::port(), $errno, $errstr, 1);
        if ($socket) {
            fclose($socket);

            return true;
        }

        return false;
    }

    /**
     * Initialises the data folder on first run, starts mysqld and makes sure the
     * app database and user exist. Blocks until MySQL accepts connections.
     */
    public static function start(): void
    {
        if (! is_file(self::mysqld())) {
            throw new RuntimeException('Bundled MySQL not found at '.self::mysqld().'. Run scripts/desktop/prepare-mysql.ps1 before building.');
        }

        // the app may boot twice (window re-open); only one may start mysqld
        $lock = fopen(self::dataDir().'.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            self::startLocked();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    protected static function startLocked(): void
    {
        $fresh = ! is_dir(self::dataDir().DIRECTORY_SEPARATOR.'mysql');
        if ($fresh) {
            @mkdir(self::dataDir(), 0775, true);
            $cmd = '"'.self::mysqld().'" --no-defaults --initialize-insecure --basedir="'.self::baseDir().'" --datadir="'.self::dataDir().'" 2>&1';
            exec($cmd, $output, $code);
            if ($code !== 0) {
                throw new RuntimeException('MySQL init failed: '.implode("\n", $output));
            }
        }

        if (! self::isRunning() && ChildProcess::get('mysql') === null) {
            ChildProcess::start(
                cmd: [
                    self::mysqld(),
                    '--no-defaults',
                    '--basedir='.self::baseDir(),
                    '--datadir='.self::dataDir(),
                    '--port='.self::port(),
                    '--bind-address=127.0.0.1',
                    '--mysqlx=OFF',
                    '--disable-log-bin',
                    '--character-set-server=utf8mb4',
                    '--collation-server=utf8mb4_unicode_ci',
                    '--sql-mode=NO_ENGINE_SUBSTITUTION',
                    '--max-allowed-packet=256M',
                    '--innodb-buffer-pool-size=256M',
                    '--console',
                ],
                alias: 'mysql',
                cwd: self::baseDir(),
                persistent: true,
            );
        }

        $deadline = time() + 90;
        while (! self::isRunning()) {
            if (time() > $deadline) {
                throw new RuntimeException('Bundled MySQL did not start on port '.self::port().'.');
            }
            usleep(500000);
        }

        self::ensureDatabase($fresh);
    }

    /** Creates the app user and database (root has no password right after init). */
    protected static function ensureDatabase(bool $fresh): void
    {
        $db = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $deadline = time() + 30;
        while (true) {
            try {
                $pdo = $fresh ? self::connect('root', '') : self::connect($user, $password);
                break;
            } catch (\PDOException $e) {
                // an earlier first start stopped before the password was set
                if (! $fresh) {
                    try {
                        $pdo = self::connect('root', '');
                        $fresh = true;
                        break;
                    } catch (\PDOException $ignored) {
                    }
                }
                if (time() > $deadline) {
                    throw $e;
                }
                usleep(500000);
            }
        }

        if ($fresh) {
            $q = fn ($v) => $pdo->quote($v);
            $pdo->exec('ALTER USER \'root\'@\'localhost\' IDENTIFIED BY '.$q($password));
            if ($user !== 'root') {
                $pdo->exec('CREATE USER IF NOT EXISTS '.$q($user).'@\'localhost\' IDENTIFIED BY '.$q($password));
                $pdo->exec('GRANT ALL PRIVILEGES ON *.* TO '.$q($user).'@\'localhost\' WITH GRANT OPTION');
            }
        }
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '', $db).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }
}
