<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SalesOrderWindowManager
{
    public const SESSION_KEY = 'so_create_windows';

    public const MAX_WINDOWS = 8;

    /** Keep unsaved SO tab drafts for 12 hours. */
    public const DRAFT_TTL_SECONDS = 43200;

    /**
     * @return array{windows: list<array{id: string, draft: array<string, mixed>|null}>, active: string|null}
     */
    public function state(): array
    {
        $raw = session(self::SESSION_KEY);
        if (! is_array($raw) || ! isset($raw['windows']) || ! is_array($raw['windows']) || $raw['windows'] === []) {
            $cached = $this->readCache();
            if ($cached !== null) {
                session([self::SESSION_KEY => $cached]);
                $raw = $cached;
            }
        }

        if (! is_array($raw) || ! isset($raw['windows']) || ! is_array($raw['windows'])) {
            return ['windows' => [], 'active' => null];
        }

        $windows = [];
        foreach ($raw['windows'] as $row) {
            if (! is_array($row) || empty($row['id']) || ! is_string($row['id'])) {
                continue;
            }
            $windows[] = [
                'id' => $row['id'],
                'draft' => isset($row['draft']) && is_array($row['draft']) ? $row['draft'] : null,
            ];
        }

        $active = isset($raw['active']) && is_string($raw['active']) ? $raw['active'] : null;
        if ($active !== null && ! collect($windows)->contains(fn (array $w) => $w['id'] === $active)) {
            $active = $windows[0]['id'] ?? null;
        }

        return ['windows' => $windows, 'active' => $active];
    }

    /**
     * @return list<array{id: string, serial: int, label: string, url: string, draft: array<string, mixed>|null}>
     */
    public function list(): array
    {
        $out = [];
        foreach ($this->state()['windows'] as $i => $window) {
            $serial = $i + 1;
            $out[] = [
                'id' => $window['id'],
                'serial' => $serial,
                'label' => 'New Sales Order '.$serial,
                'url' => route('sales.orders.create', ['w' => $window['id']]),
                'draft' => $window['draft'],
            ];
        }

        return $out;
    }

    public function count(): int
    {
        return count($this->state()['windows']);
    }

    public function activeId(): ?string
    {
        return $this->state()['active'];
    }

    public function ensureOne(): string
    {
        $state = $this->state();
        if ($state['windows'] !== []) {
            $id = $state['active'] ?? $state['windows'][0]['id'];
            $this->setActive($id);

            return $id;
        }

        return $this->open();
    }

    public function open(): string
    {
        $state = $this->state();
        if (count($state['windows']) >= self::MAX_WINDOWS) {
            $id = $state['active'] ?? $state['windows'][0]['id'];
            $this->setActive($id);

            return $id;
        }

        $id = (string) Str::uuid();
        $state['windows'][] = ['id' => $id, 'draft' => null];
        $state['active'] = $id;
        $this->put($state);

        return $id;
    }

    /**
     * Close a window. Returns the next active window id, or null if none remain.
     */
    public function close(string $id): ?string
    {
        $state = $this->state();
        $index = null;
        foreach ($state['windows'] as $i => $window) {
            if ($window['id'] === $id) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return $state['active'];
        }

        array_splice($state['windows'], $index, 1);

        if ($state['windows'] === []) {
            $state['active'] = null;
            $this->put($state);
            $this->forgetCache();

            return null;
        }

        if ($state['active'] === $id) {
            $neighbor = $state['windows'][max(0, $index - 1)]['id']
                ?? $state['windows'][0]['id'];
            $state['active'] = $neighbor;
        }

        $this->put($state);

        return $state['active'];
    }

    public function setActive(string $id): void
    {
        $state = $this->state();
        if (! collect($state['windows'])->contains(fn (array $w) => $w['id'] === $id)) {
            return;
        }
        $state['active'] = $id;
        $this->put($state);
    }

    public function has(string $id): bool
    {
        return collect($this->state()['windows'])->contains(fn (array $w) => $w['id'] === $id);
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    public function saveDraft(string $id, array $draft): void
    {
        $state = $this->state();
        foreach ($state['windows'] as $i => $window) {
            if ($window['id'] !== $id) {
                continue;
            }
            $state['windows'][$i]['draft'] = $draft;
            $this->put($state);

            return;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function loadDraft(string $id): ?array
    {
        foreach ($this->state()['windows'] as $window) {
            if ($window['id'] === $id) {
                return $window['draft'];
            }
        }

        return null;
    }

    public function clearDraft(string $id): void
    {
        $state = $this->state();
        foreach ($state['windows'] as $i => $window) {
            if ($window['id'] !== $id) {
                continue;
            }
            $state['windows'][$i]['draft'] = null;
            $this->put($state);

            return;
        }
    }

    /**
     * @param  array{windows: list<array{id: string, draft: array<string, mixed>|null}>, active: string|null}  $state
     */
    protected function put(array $state): void
    {
        session([self::SESSION_KEY => $state]);
        $this->writeCache($state);
    }

    protected function cacheKey(): ?string
    {
        $userId = auth()->id();
        if (! $userId) {
            return null;
        }

        return 'so_create_windows:user:'.$userId;
    }

    /**
     * @return array{windows: list<array{id: string, draft: array<string, mixed>|null}>, active: string|null}|null
     */
    protected function readCache(): ?array
    {
        $key = $this->cacheKey();
        if ($key === null) {
            return null;
        }

        $cached = Cache::get($key);
        if (! is_array($cached) || ! isset($cached['windows']) || ! is_array($cached['windows'])) {
            return null;
        }

        return $cached;
    }

    /**
     * @param  array{windows: list<array{id: string, draft: array<string, mixed>|null}>, active: string|null}  $state
     */
    protected function writeCache(array $state): void
    {
        $key = $this->cacheKey();
        if ($key === null) {
            return;
        }

        if ($state['windows'] === []) {
            Cache::forget($key);

            return;
        }

        Cache::put($key, $state, self::DRAFT_TTL_SECONDS);
    }

    protected function forgetCache(): void
    {
        $key = $this->cacheKey();
        if ($key !== null) {
            Cache::forget($key);
        }
    }
}
