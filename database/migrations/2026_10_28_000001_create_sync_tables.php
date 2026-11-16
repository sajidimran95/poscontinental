<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Filled by database triggers on every synced table.
        Schema::create('sync_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('table_name', 64);
            $table->string('op', 1); // i, u, d
            $table->text('pk');      // JSON of primary key values
            $table->text('deltas')->nullable(); // JSON of counter changes (updates only)
            $table->unsignedInteger('origin')->default(0); // 0 = this node, else sync_nodes.node_no
            $table->dateTime('created_at', 6)->useCurrent();
            $table->index(['origin', 'id']);
            $table->index('created_at');
        });

        // Server: registered desktop PCs.
        Schema::create('sync_nodes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('node_no')->unique();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->string('invoice_prefix', 20);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('last_pull_cursor')->default(0);
            $table->dateTime('last_push_at')->nullable();
            $table->dateTime('last_pull_at')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->string('app_version', 30)->nullable();
            $table->timestamps();
        });

        // Server: pushed batches already applied, so a resend is never applied twice.
        Schema::create('sync_receipts', function (Blueprint $table) {
            $table->string('batch_id', 36)->primary();
            $table->unsignedInteger('node_no');
            $table->unsignedInteger('changes')->default(0);
            $table->dateTime('created_at')->useCurrent();
        });

        // Both: key/value state (client cursor, pending batch, last error...).
        Schema::create('sync_state', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->longText('value')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        // Both: rows that could not be applied (unique key clash etc.) for admin review.
        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('direction', 4); // push | pull
            $table->unsignedInteger('node_no')->default(0);
            $table->string('table_name', 64);
            $table->text('pk');
            $table->longText('row')->nullable();
            $table->text('error');
            $table->boolean('resolved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
        Schema::dropIfExists('sync_state');
        Schema::dropIfExists('sync_receipts');
        Schema::dropIfExists('sync_nodes');
        Schema::dropIfExists('sync_log');
    }
};
