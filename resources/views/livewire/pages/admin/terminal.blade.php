<?php

use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('Terminal')] class extends Component
{
    public string $output = '';

    public string $status = '';

    public string $error = '';

    public function clearCache(): void
    {
        $this->runCommands([
            'config:clear' => [],
            'cache:clear' => [],
            'view:clear' => [],
            'route:clear' => [],
        ], 'Cache cleared successfully.');
    }

    public function runMigrations(): void
    {
        $this->runCommands([
            'migrate' => ['--force' => true],
        ], 'Migrations completed successfully.');
    }

    public function optimizeClear(): void
    {
        $this->runCommands([
            'optimize:clear' => [],
        ], 'Optimize clear completed.');
    }

    public function storageLink(): void
    {
        $this->runCommands([
            'storage:link' => ['--force' => true],
        ], 'Storage link created (public/storage → storage/app/public).');
    }

    public function seedUom(): void
    {
        $this->runCommands([
            'db:seed' => [
                '--class' => 'UomScheduleSeeder',
                '--force' => true,
            ],
        ], 'UOM schedules seeded successfully.');
    }

    public function runSeeders(): void
    {
        $this->runCommands([
            'db:seed' => [
                '--class' => 'DatabaseSeeder',
                '--force' => true,
            ],
        ], 'Database seeder completed.');
    }

    /**
     * @param  array<string, array<string, mixed>>  $commands
     */
    protected function runCommands(array $commands, string $successMessage): void
    {
        $this->output = '';
        $this->status = '';
        $this->error = '';

        try {
            $lines = [];
            foreach ($commands as $command => $params) {
                $exit = Artisan::call($command, $params);
                $buffer = trim(Artisan::output());
                $cli = '> php artisan '.$command;
                foreach ($params as $key => $value) {
                    if (is_bool($value)) {
                        if ($value) {
                            $cli .= ' '.$key;
                        }
                    } else {
                        $cli .= ' '.$key.'='.$value;
                    }
                }
                $lines[] = $cli;
                $lines[] = $buffer !== '' ? $buffer : '(no output)';
                $lines[] = 'Exit: '.$exit;
                $lines[] = '';
                if ($exit !== 0) {
                    throw new \RuntimeException($command.' failed (exit '.$exit.').');
                }
            }
            $this->output = implode("\n", $lines);
            $this->status = $successMessage;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $extra = trim(Artisan::output());
            if ($this->output === '' && $extra !== '') {
                $this->output = $extra;
            }
        }
    }
}; ?>

<div class="desk-page">
    <div class="desk-main" style="max-width:48rem">
        <x-action-bar title="Terminal" />

        @if ($status)
            <div class="desk-flash" role="status">{{ $status }}</div>
        @endif
        @if ($error)
            <div class="desk-flash" style="background:#fef2f2;border-color:#fecaca;color:#991b1b" role="alert">{{ $error }}</div>
        @endif

        <div class="inv-card" style="margin:1rem 0.85rem">
            <div class="inv-card-title">Maintenance commands</div>
            <p class="item-hint" style="border:0;margin:0 0 1rem;padding:0">
                Run common Artisan tasks from the POS. Use after deploying updates or when pages show stale data.
            </p>

            <div class="rpt-actions" style="display:flex;flex-wrap:wrap;gap:0.5rem">
                <button
                    type="button"
                    wire:click="clearCache"
                    wire:loading.attr="disabled"
                    wire:target="clearCache,runMigrations,optimizeClear,storageLink,seedUom,runSeeders"
                    class="desk-btn desk-btn-primary"
                >
                    Clear Cache
                </button>
                <button
                    type="button"
                    wire:click="runMigrations"
                    wire:loading.attr="disabled"
                    wire:target="clearCache,runMigrations,optimizeClear,storageLink,seedUom,runSeeders"
                    class="desk-btn"
                    wire:confirm="Run database migrations now? This updates the schema."
                >
                    Run Migrations
                </button>
                <button
                    type="button"
                    wire:click="optimizeClear"
                    wire:loading.attr="disabled"
                    wire:target="clearCache,runMigrations,optimizeClear,storageLink,seedUom,runSeeders"
                    class="desk-btn"
                >
                    Optimize Clear
                </button>
                <button
                    type="button"
                    wire:click="storageLink"
                    wire:loading.attr="disabled"
                    wire:target="clearCache,runMigrations,optimizeClear,storageLink,seedUom,runSeeders"
                    class="desk-btn"
                    wire:confirm="Create or refresh the public/storage link? Needed for item images and uploads."
                >
                    Storage Link
                </button>
            </div>

            <div class="inv-card-title" style="margin-top:1.25rem">Seeders</div>
            <div class="rpt-actions" style="display:flex;flex-wrap:wrap;gap:0.5rem">
                <button
                    type="button"
                    wire:click="seedUom"
                    wire:loading.attr="disabled"
                    wire:target="clearCache,runMigrations,optimizeClear,storageLink,seedUom,runSeeders"
                    class="desk-btn desk-btn-primary"
                >
                    Seed UOM Schedules
                </button>
                <button
                    type="button"
                    wire:click="runSeeders"
                    wire:loading.attr="disabled"
                    wire:target="clearCache,runMigrations,optimizeClear,storageLink,seedUom,runSeeders"
                    class="desk-btn"
                    wire:confirm="Run the full DatabaseSeeder? Existing records (like company CWI) will be skipped."
                >
                    Run Database Seeder
                </button>
            </div>

            <p class="item-hint" style="border:0;margin:0.75rem 0 0;padding:0;font-size:0.75rem;color:#64748b">
                <strong>Storage Link</strong> — <code>php artisan storage:link --force</code> (item images) &nbsp;·&nbsp;
                <strong>Seed UOM</strong> — adds EA, BX, CS, CTN… (safe, skips existing) &nbsp;·&nbsp;
                <strong>Database Seeder</strong> — full demo seed (<code>DatabaseSeeder</code>)
            </p>
        </div>

        <div class="inv-card" style="margin:0 0.85rem 1rem">
            <div class="inv-card-title">Output</div>
            <pre
                class="font-mono"
                style="margin:0;min-height:10rem;max-height:22rem;overflow:auto;padding:0.75rem;background:#0f172a;color:#e2e8f0;border-radius:6px;font-size:0.75rem;line-height:1.45;white-space:pre-wrap"
                wire:loading.class="opacity-60"
                wire:target="clearCache,runMigrations,optimizeClear,storageLink,seedUom,runSeeders"
            >{{ $output !== '' ? $output : 'No commands run yet.' }}</pre>
            <p wire:loading wire:target="clearCache,runMigrations,optimizeClear,storageLink,seedUom,runSeeders" class="item-hint" style="border:0;margin:0.5rem 0 0;padding:0">
                Running…
            </p>
        </div>
    </div>
</div>
