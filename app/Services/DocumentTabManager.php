<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class DocumentTabManager
{
    public const SESSION_KEY = 'pos_document_tabs';

    public const MAX_TABS = 20;

    public const DRAFT_TTL_SECONDS = 43200;

    /**
     * Routes that only keep one tab (re-click focuses existing).
     * sales.orders.create is handled by SalesOrderWindowManager instead.
     */
    public function isSingleton(string $route): bool
    {
        return $route !== 'sales.orders.create';
    }

    /**
     * @return array{tabs: list<array{id: string, label: string, route: string, url: string}>, active: string|null}
     */
    public function state(): array
    {
        $raw = session(self::SESSION_KEY);
        if (! is_array($raw) || ! isset($raw['tabs']) || ! is_array($raw['tabs']) || $raw['tabs'] === []) {
            $cached = $this->readCache();
            if ($cached !== null) {
                session([self::SESSION_KEY => $cached]);
                $raw = $cached;
            }
        }

        if (! is_array($raw) || ! isset($raw['tabs']) || ! is_array($raw['tabs'])) {
            return ['tabs' => [], 'active' => null];
        }

        $tabs = [];
        foreach ($raw['tabs'] as $row) {
            if (! is_array($row) || empty($row['id']) || empty($row['route']) || empty($row['url'])) {
                continue;
            }
            // SO create windows live in SalesOrderWindowManager
            if (($row['route'] ?? '') === 'sales.orders.create') {
                continue;
            }
            $tabs[] = [
                'id' => (string) $row['id'],
                'label' => (string) ($row['label'] ?? $row['route']),
                'route' => (string) $row['route'],
                'url' => (string) $row['url'],
            ];
        }

        $active = isset($raw['active']) && is_string($raw['active']) ? $raw['active'] : null;
        if ($active !== null && ! collect($tabs)->contains(fn (array $t) => $t['id'] === $active)) {
            $active = $tabs[0]['id'] ?? null;
        }

        return ['tabs' => $tabs, 'active' => $active];
    }

    /**
     * @return list<array{id: string, label: string, route: string, url: string}>
     */
    public function list(): array
    {
        return $this->state()['tabs'];
    }

    public function count(): int
    {
        return count($this->state()['tabs']);
    }

    public function activeId(): ?string
    {
        return $this->state()['active'];
    }

    public function has(string $id): bool
    {
        return collect($this->state()['tabs'])->contains(fn (array $t) => $t['id'] === $id);
    }

    /**
     * Open or focus a document tab. Returns tab id.
     */
    public function openOrFocus(string $label, string $route, string $url): string
    {
        if ($route === 'sales.orders.create' || $route === 'home') {
            return '';
        }

        $state = $this->state();

        if ($this->isSingleton($route)) {
            foreach ($state['tabs'] as $i => $tab) {
                if ($tab['route'] === $route) {
                    $state['tabs'][$i]['label'] = $label;
                    $state['tabs'][$i]['url'] = $url;
                    $state['active'] = $tab['id'];
                    $this->put($state);

                    return $tab['id'];
                }
            }
        } else {
            foreach ($state['tabs'] as $tab) {
                if ($tab['url'] === $url) {
                    $state['active'] = $tab['id'];
                    $this->put($state);

                    return $tab['id'];
                }
            }
        }

        if (count($state['tabs']) >= self::MAX_TABS) {
            array_shift($state['tabs']);
        }

        $id = (string) Str::uuid();
        $state['tabs'][] = [
            'id' => $id,
            'label' => $label,
            'route' => $route,
            'url' => $url,
        ];
        $state['active'] = $id;
        $this->put($state);

        return $id;
    }

    /**
     * Sync the current request page into the tab strip.
     */
    public function syncCurrent(?string $routeName, string $label, string $url): void
    {
        if (! $routeName || $routeName === 'home' || $routeName === 'sales.orders.create') {
            return;
        }

        if (! Route::has($routeName) && ! str_contains($routeName, '.')) {
            return;
        }

        $this->openOrFocus($label, $routeName, $url);
    }

    public function setActive(string $id): void
    {
        $state = $this->state();
        if (! collect($state['tabs'])->contains(fn (array $t) => $t['id'] === $id)) {
            return;
        }
        $state['active'] = $id;
        $this->put($state);
    }

    /**
     * Close a tab.
     *
     * @return string|null Next URL, null for Home, or "__back__" to stay on current page
     */
    public function close(string $id): ?string
    {
        $state = $this->state();
        $index = null;
        foreach ($state['tabs'] as $i => $tab) {
            if ($tab['id'] === $id) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            return '__back__';
        }

        $wasActive = $state['active'] === $id;
        array_splice($state['tabs'], $index, 1);

        if ($state['tabs'] === []) {
            $state['active'] = null;
            $this->put($state);
            $this->forgetCache();

            return $wasActive ? null : '__back__';
        }

        if ($wasActive) {
            $neighbor = $state['tabs'][max(0, $index - 1)] ?? $state['tabs'][0];
            $state['active'] = $neighbor['id'];
            $this->put($state);

            return $neighbor['url'];
        }

        $this->put($state);

        return '__back__';
    }

    public function find(string $id): ?array
    {
        foreach ($this->state()['tabs'] as $tab) {
            if ($tab['id'] === $id) {
                return $tab;
            }
        }

        return null;
    }

    /**
     * @param  array{tabs: list<array{id: string, label: string, route: string, url: string}>, active: string|null}  $state
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

        return 'pos_document_tabs:user:'.$userId;
    }

    /**
     * @return array{tabs: list<array{id: string, label: string, route: string, url: string}>, active: string|null}|null
     */
    protected function readCache(): ?array
    {
        $key = $this->cacheKey();
        if ($key === null) {
            return null;
        }

        $cached = Cache::get($key);

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param  array{tabs: list<array{id: string, label: string, route: string, url: string}>, active: string|null}  $state
     */
    protected function writeCache(array $state): void
    {
        $key = $this->cacheKey();
        if ($key === null) {
            return;
        }

        if ($state['tabs'] === []) {
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
