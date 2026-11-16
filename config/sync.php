<?php

/*
|--------------------------------------------------------------------------
| Offline desktop <-> online server sync
|--------------------------------------------------------------------------
|
| "server" = the main online installation (cPanel / Laragon). Desktop PCs
| register against it and push/pull changes through /api/sync/*.
| "client" = a desktop (NativePHP) installation with its own local MySQL.
| A client's node settings (server url, token, node number, invoice prefix)
| are written by the setup screen to storage/app/sync/node.json.
|
| Same architecture as japspos (docs/offline-desktop/README.md). Offline is
| bundled MySQL (not SQLite) so change-capture triggers and ID lanes work.
| Push batches use UUID batch_id for idempotent receipts.
*/

return [

    // server | client | off
    'mode' => env('SYNC_MODE', 'off'),

    /*
     * Every node creates auto-increment IDs on its own lane so IDs never clash:
     * id % id_step == node_no % id_step. The server is node 1, desktops are 2..id_step.
     * MUST be identical on the server and every desktop and must never change.
     */
    'id_step' => (int) env('SYNC_ID_STEP', 100),

    'server_node_no' => 1,

    // Rows changed in one push/pull request.
    'batch_size' => (int) env('SYNC_BATCH_SIZE', 500),

    // Rows per page when a new desktop downloads the full database.
    'snapshot_page_size' => (int) env('SYNC_SNAPSHOT_PAGE_SIZE', 1000),

    // Server only hands out log entries older than this, so entries of
    // transactions still committing are not skipped by a pull cursor.
    'pull_lag_seconds' => (int) env('SYNC_PULL_LAG_SECONDS', 10),

    // Server keeps change log entries this long. A desktop offline for longer must re-download.
    'log_retention_days' => (int) env('SYNC_LOG_RETENTION_DAYS', 45),

    // Seconds between sync rounds when `sync:run --loop` runs on a desktop.
    'interval' => (int) env('SYNC_INTERVAL', 60),

    'http_timeout' => (int) env('SYNC_HTTP_TIMEOUT', 120),

    /*
     * Tables never synced (local runtime data). Supports * wildcards.
     */
    'exclude_tables' => [
        'sync_*',
        'migrations',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_resets',
        'password_reset_tokens',
        'personal_access_tokens',
        'telescope_*',
        'pulse_*',
    ],

    /*
     * Copied once when a desktop is set up, but not kept in sync afterwards.
     */
    'snapshot_only_tables' => [
        // none for continental — document numbers use per-desktop prefixes instead
    ],

    /*
     * Counter columns that several nodes change at the same time (stock, balances).
     * They travel as +/- deltas instead of being overwritten.
     */
    'delta_columns' => [
        'items' => ['quantity_in_stock', 'allocated_qty', 'on_order_qty', 'back_order_qty'],
        'customers' => ['balance'],
        'sales_order_lines' => ['qty_shipped'],
        'purchase_order_lines' => ['qty_received'],
        'item_batches' => ['quantity'],
    ],

    /*
     * Columns each node keeps for itself (never overwritten by the other side).
     */
    'local_columns' => [
        // reserved for per-node counters if added later
    ],
];
