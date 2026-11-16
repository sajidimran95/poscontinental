# Offline desktop app + auto sync

The online POS stays the main system. Each shop PC runs a **desktop app**: the
same POS with its own local MySQL, so it keeps working when the internet or the
server is down. Every minute the desktop sends its changes to the server and gets
the changes made online or on other PCs.

> Same architecture as japspos. Offline uses **bundled MySQL** (NativePHP’s
> default SQLite cannot run the change-capture triggers or ID lanes). Push batches
> use a **UUID `batch_id`** so resends are never applied twice.

```
 Desktop PC 1 ─┐    push: my changes        ┌───────────────────────┐
 Desktop PC 2 ─┼──────────────────────────► │  Online server (main) │
 Desktop PC n ─┘ ◄────────────────────────  │  MySQL                │
                    pull: everyone else's   └───────────────────────┘
```

## How it works

| Topic | How |
|---|---|
| What syncs | Every table (all modules), plus files in `public/uploads`. Excluded: sessions, cache, jobs, tokens — see `config/sync.php`. |
| Change capture | MySQL triggers (`zsync_*`) write each insert/update/delete to `sync_log`. Created by `php artisan sync:triggers`, refreshed automatically after every `migrate`. |
| No ID clashes | Every node gets its own auto-increment lane: server IDs end in `01`, PC node 2 in `02`, node 3 in `03`… (`SYNC_ID_STEP=100`, max 99 PCs). |
| Stock & counters | `items.quantity_in_stock` / allocated / on-order, customer `balance`, line `qty_shipped` / `qty_received` travel as **+/- deltas**. |
| Document numbers | Numbers made on a PC get its prefix: `PC1-243074`, `PC1-100001`. Server numbers stay numeric. |
| Same record edited twice | The latest synced edit wins. Failed rows are listed under **File → Sync status**. |
| Safe resends | A push is saved before sending and has a UUID batch id; the server applies a batch only once. |

## 1. Server setup — once

1. Deploy this code and run `composer install --no-dev` and `php artisan migrate --force`.
2. In the server `.env` add:
   ```
   SYNC_MODE=server
   SYNC_ID_STEP=100
   ```
3. Create the triggers:
   ```
   php artisan sync:triggers
   ```
   If this fails with a privilege error: the database user needs the **TRIGGER** privilege,
   and if binary logging is on, set `log_bin_trust_function_creators=1`.
4. Make sure the Laravel scheduler cron runs (cleans the sync log daily):
   `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`
5. Register each PC and note its token (shown once). Easiest: log in as Admin →
   **File → Desktop devices** → *Add device & create token*. Or:
   ```
   php artisan sync:device add "Main shop PC1" --prefix=PC1
   php artisan sync:device              # list devices
   php artisan sync:device disable 2
   php artisan sync:device token 2
   ```

## 2. Build the desktop installer (developer PC)

Needs PHP 8.3+, Composer, Node.js 20+.

```
copy .env.desktop.example .env.desktop      # set APP_KEY (php artisan key:generate --show) and DB_PASSWORD
powershell -ExecutionPolicy Bypass -File scripts/desktop/build.ps1 -PhpExe C:\tools\php-static\php.exe
```

* `-PhpExe` must be a **single-file (static) PHP 8.4** with `pdo_mysql`, `mysqli`, `xmlreader`,
  `xmlwriter` (the script checks). NativePHP's own PHP only has SQLite.
  Use the "spc-max" Windows CLI build:
  https://dl.static-php.dev/static-php-cli/windows/spc-max/
* `scripts/desktop/prepare-mysql.ps1` copies a trimmed MySQL 8.4 into `extras/mysql`
  (done automatically by build.ps1).
* The installer is written to `dist/`.
* Raise `NATIVEPHP_APP_VERSION` in `.env.desktop` for every new release.

## 3. Install on a shop PC

1. Run the installer from `dist/`.
2. The app starts its own MySQL (port 3307, data in `%APPDATA%\<app>\mysql-data`) and
   opens the **Connect this PC** screen.
3. Enter the online server address and the device token, click **Connect and download data**.
4. Log in with the usual POS account. It syncs every minute from now on.

## Daily use

* Works the same online and offline. Offline sales are sent automatically when the
  internet is back.
* **File → Sync status** (or `/desktop/sync`): last sync, pending changes, **Sync now**.
* A PC sees another PC's offline sales after **both** have synced.

## Updating

Server and desktops must run the **same version** (same migrations). After deploying a
new version on the server, build and install the new desktop version. Until then the
desktop shows *"Desktop app version does not match the server"* and keeps working offline.

## Desktop commands

```
php artisan sync:run                     # one sync round
php artisan sync:run --loop              # keep syncing
php artisan sync:run --status            # status as JSON
php artisan sync:run --retry-conflicts   # resend refused rows
php artisan sync:run --bootstrap         # fresh copy from server
php artisan sync:run --setup --server=https://pos.example.com --token=XXXX
```

## Things to know

* A PC offline longer than `SYNC_LOG_RETENTION_DAYS` (45) must re-download after pushing local sales.
* On the server, add records only through the POS — rows inserted in phpMyAdmin skip ID lanes.
* Stock can go below zero if two PCs sell the last item while offline — totals stay correct.
* Files are copied (new/changed), never deleted.
