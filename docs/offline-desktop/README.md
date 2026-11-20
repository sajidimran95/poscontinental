# Offline desktop app + auto sync

The online POS stays the main system. Each shop PC runs a **desktop app** (`.exe` installer):
the same POS with the **same screens as the web**, inside its own window, with its own local
database, so it keeps working when the internet or the server is down. Every minute the
desktop sends its changes to the server and gets the changes made online or on other PCs.

> Same architecture as japspos. Offline data lives in a **bundled MySQL 8.4** shipped inside
> the installer, not SQLite: the POS uses MySQL-only SQL, and the change-capture triggers and
> per-PC ID lanes rely on MySQL. Push batches use a **UUID `batch_id`** so resends are never
> applied twice. Sync responses are gzip-compressed.

```
 Desktop PC 1 ─┐    push: my changes        ┌───────────────────────┐
 Desktop PC 2 ─┼──────────────────────────► │  Online server (main) │
 Desktop PC n ─┘ ◄────────────────────────  │  MySQL                │
                    pull: everyone else's   └───────────────────────┘
```

## How it works

| Topic | How |
|---|---|
| What syncs | Every table, plus files in `public/uploads`. Excluded: sessions, cache, jobs, tokens — see `config/sync.php`. |
| Change capture | MySQL triggers (`zsync_*`) write each insert/update/delete to `sync_log`. Created by `php artisan sync:triggers`, refreshed automatically after every `migrate`. |
| No ID clashes | Every node gets its own auto-increment lane: server IDs end in `01`, PC node 2 in `02`, node 3 in `03`… (`SYNC_ID_STEP=100`, max 99 PCs). |
| Stock & counters | `items.quantity_in_stock` / allocated / on-order, customer `balance`, line `qty_shipped` / `qty_received` travel as **+/- deltas**. |
| Document numbers | Numbers made on a PC get its **desktop prefix**: `PC2-243074`, `PC2-100001`. Server numbers stay numeric. |
| Same record edited twice | The latest synced edit wins. Failed rows are listed under **Sync → Sync status**. |
| Safe resends | A push is saved before sending and has a UUID batch id; the server applies a batch only once. |
| Slow / unstable internet | Sync calls retry dropped connections; the first download asks for smaller pages when the host keeps dropping big ones. |

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
5. Register each PC. Log in as Admin → **File → Desktop devices**:
   * **Add device & generate key** — device name + **desktop prefix** (*Generate* fills the next
     free one, e.g. `PC4`; empty = automatic). The **desktop key** is shown once, with Copy buttons
     for the key and the server address.
   * **Edit** — rename a PC or change its prefix. The PC uses the new prefix for new documents
     after its next sync; existing document numbers do not change.
   * **New key** (the old key stops working, reconnect the PC), **Disable / Enable**.

   Or from the command line:
   ```
   php artisan sync:device add "Main shop PC1" --prefix=PC1
   php artisan sync:device              # list devices
   php artisan sync:device disable 2
   php artisan sync:device token 2
   ```

## 2. Build the desktop installer (developer PC)

Needs PHP 8.3+, Composer, Node.js 20+.

1. A **single-file (static) PHP 8.4** with `pdo_mysql`, `mysqli`, `xmlreader`, `xmlwriter`
   (NativePHP's own PHP only has SQLite and could not reach the bundled MySQL). Download the
   "spc-max" Windows CLI build `php-8.4.x-cli-win.zip` from
   https://dl.static-php.dev/static-php-cli/windows/spc-max/ and unzip `php.exe` to
   `<drive>:\laragon\bin\php-static\` (the build script looks there, in `tools\php-static\`
   and `C:\tools\php-static\`; or pass `-PhpExe <path>`). The script checks the extensions.
2. `.env.desktop` (copy of `.env.desktop.example`, with `APP_KEY` from
   `php artisan key:generate --show` and a long random `DB_PASSWORD`).
3. Build:
   ```
   powershell -ExecutionPolicy Bypass -File scripts/desktop/build.ps1
   ```

* `scripts/desktop/prepare-mysql.ps1` copies a trimmed MySQL 8.4 (~100 MB) into `extras/mysql`
  (done automatically by build.ps1; it picks the newest MySQL 8 under `<drive>:\laragon\bin\mysql`,
  or `-Source <folder>`).
* The installer is written to `dist/`.
* During the build `.env` is temporarily replaced by `.env.desktop` (restored afterwards).
* Raise `NATIVEPHP_APP_VERSION` in `.env.desktop` for every new release.

## 3. Install on a shop PC

1. Run the installer from `dist/`.
2. The app starts its own MySQL (port 3307, data in `%APPDATA%\<app>\mysql-data`) and
   opens the **Connect this PC** screen.
3. Enter the online server address and the **desktop key**, click **Connect and download data**.
   The download runs in the background with a live % progress bar and log. If it stops,
   **Try again** restarts it.
4. Log in with the usual POS account. It syncs every minute from now on.

## Daily use

* Works the same online and offline. Offline sales are sent automatically when the
  internet is back.
* **Sync → Sync status** (app menu, or `/desktop/sync`): last sync, pending changes, **Sync now**.
* **Sync → Receipt printer**: pick a Windows printer and paper size, tick *Print directly*.
  Every Print button (invoice, pick list, reports) then prints on that printer without the
  dialog, on this PC only. If the printer fails, the normal print dialog opens instead.
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
php artisan sync:download                # first download with progress file (used by the setup screen)
```

## Things to know

* A PC offline longer than `SYNC_LOG_RETENTION_DAYS` (45) must re-download after pushing local sales.
* On the server, add records only through the POS — rows inserted in phpMyAdmin skip ID lanes.
* Stock can go below zero if two PCs sell the last item while offline — totals stay correct.
* Files are copied (new/changed), never deleted.
* Email and other online services work on a desktop only while it is online.
