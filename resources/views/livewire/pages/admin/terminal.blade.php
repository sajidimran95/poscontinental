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
            'config:clear' => 'Clear config cache',
            'cache:clear' => 'Clear application cache',
            'view:clear' => 'Clear compiled views',
            'route:clear' => 'Clear route cache',
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
            'optimize:clear' => 'Clear all caches',
        ], 'Optimize clear completed.');
    }

    /**
     * @param  array<string, array<string, mixed>|string>  $commands
     */
    protected function runCommands(array $commands, string $successMessage): void
    {
        $this->output = '';
        $this->status = '';
        $this->error = '';

        try {
            $lines = [];
            foreach ($commands as $command => $options) {
                $params = is_array($options) ? $options : [];
                $label = is_string($options) ? $options : $command;
                $exit = Artisan::call($command, $params);
                $buffer = trim(Artisan::output());
                $lines[] = '> php artisan '.$command.(is_array($options) && isset($options['--force']) ? ' --force' : '');
                $lines[] = $buffer !== '' ? $buffer : '(no output)';
                $lines[] = 'Exit: '.$exit;
                $lines[] = '';
                if ($exit !== 0 && $command === 'migrate') {
                    throw new \RuntimeException('Migration failed (exit '.$exit.').');
                }
            }
            $this->output = implode("\n", $lines);
            $this->status = $successMessage;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            if ($this->output === '') {
                $this->output = Artisan::output();
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
                    wire:target="clearCache,runMigrations,optimizeClear"
                    class="desk-btn desk-btn-primary"
                >
                    Clear Cache
                </button>
                <button
                    type="button"
                    wire:click="runMigrations"
                    wire:loading.attr="disabled"
                    wire:target="clearCache,runMigrations,optimizeClear"
                    class="desk-btn"
                    wire:confirm="Run database migrations now? This updates the schema."
                >
                    Run Migrations
                </button>
                <button
                    type="button"
                    wire:click="optimizeClear"
                    wire:loading.attr="disabled"
                    wire:target="clearCache,runMigrations,optimizeClear"
                    class="desk-btn"
                >
                    Optimize Clear
                </button>
            </div>

            <p class="item-hint" style="border:0;margin:0.75rem 0 0;padding:0;font-size:0.75rem;color:#64748b">
                <strong>Clear Cache</strong> — config, app, views, routes &nbsp;·&nbsp;
                <strong>Run Migrations</strong> — <code>php artisan migrate --force</code> &nbsp;·&nbsp;
                <strong>Optimize Clear</strong> — full Laravel cache reset
            </p>
        </div>

        <div class="inv-card" style="margin:0 0.85rem 1rem">
            <div class="inv-card-title">Output</div>
            <pre
                class="font-mono"
                style="margin:0;min-height:10rem;max-height:22rem;overflow:auto;padding:0.75rem;background:#0f172a;color:#e2e8f0;border-radius:6px;font-size:0.75rem;line-height:1.45;white-space:pre-wrap"
                wire:loading.class="opacity-60"
                wire:target="clearCache,runMigrations,optimizeClear"
            >{{ $output !== '' ? $output : 'No commands run yet.' }}</pre>
            <p wire:loading wire:target="clearCache,runMigrations,optimizeClear" class="item-hint" style="border:0;margin:0.5rem 0 0;padding:0">
                Running…
            </p>
        </div>
    </div>
</div>
